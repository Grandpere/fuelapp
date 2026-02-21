<?php

declare(strict_types=1);

/*
 * This file is part of a FuelApp project.
 *
 * (c) Lorenzo Marozzo <lorenzo.marozzo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Functional\Import;

use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class ImportReviewApiTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ImportJobRepository $importJobRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private HttpKernelInterface $httpKernel;
    private ?TerminableInterface $terminableKernel = null;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = static::getContainer();

        $kernel = $container->get(HttpKernelInterface::class);
        if (!$kernel instanceof HttpKernelInterface) {
            throw new RuntimeException('HttpKernel service is invalid.');
        }
        $this->httpKernel = $kernel;
        $this->terminableKernel = $kernel instanceof TerminableInterface ? $kernel : null;

        $em = $container->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service is invalid.');
        }
        $this->em = $em;

        $repository = $container->get(ImportJobRepository::class);
        if (!$repository instanceof ImportJobRepository) {
            throw new RuntimeException('ImportJobRepository service is invalid.');
        }
        $this->importJobRepository = $repository;

        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testAnonymousCannotFinalizeImportReview(): void
    {
        $response = $this->request('POST', '/api/imports/018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01/finalize');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testUserCanInspectAndFinalizeNeedsReviewImport(): void
    {
        $email = 'import.review@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);
        $this->em->flush();

        $job = ImportJob::createQueued(
            $user->getId()->toRfc4122(),
            'local',
            '2026/02/21/file.pdf',
            'file.pdf',
            'application/pdf',
            1024,
            str_repeat('a', 64),
        );
        $job->markNeedsReview(json_encode([
            'fingerprint' => 'checksum-sha256:v1:'.str_repeat('a', 64),
            'parsedDraft' => [
                'issues' => ['station_name_missing'],
            ],
            'creationPayload' => [
                'issuedAt' => '2026-02-21T10:00:00+00:00',
                'stationName' => 'Total',
                'stationStreetName' => '1 Rue A',
                'stationPostalCode' => '75001',
                'stationCity' => 'Paris',
                'lines' => [[
                    'fuelType' => 'diesel',
                    'quantityMilliLiters' => 10000,
                    'unitPriceDeciCentsPerLiter' => 1800,
                    'vatRatePercent' => 20,
                ]],
            ],
        ], JSON_THROW_ON_ERROR));
        $this->importJobRepository->save($job);

        $token = $this->apiLogin($email, $password);

        $showResponse = $this->request(
            'GET',
            '/api/imports/'.$job->id()->toString(),
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_OK, $showResponse->getStatusCode());
        /** @var array{status: string, reviewRequired: bool, canAutoFinalize: bool} $showPayload */
        $showPayload = json_decode((string) $showResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(ImportJobStatus::NEEDS_REVIEW->value, $showPayload['status']);
        self::assertTrue($showPayload['reviewRequired']);
        self::assertTrue($showPayload['canAutoFinalize']);

        $finalizeResponse = $this->request(
            'POST',
            '/api/imports/'.$job->id()->toString().'/finalize',
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/ld+json',
            ],
            json_encode([], JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_OK, $finalizeResponse->getStatusCode());

        /** @var array{status: string, finalizedReceiptId: ?string} $finalizePayload */
        $finalizePayload = json_decode((string) $finalizeResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(ImportJobStatus::PROCESSED->value, $finalizePayload['status']);
        self::assertNotNull($finalizePayload['finalizedReceiptId']);

        $receiptCountRaw = $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM receipts');
        self::assertIsNumeric($receiptCountRaw);
        $receiptCount = (int) $receiptCountRaw;
        self::assertSame(1, $receiptCount);
    }

    /** @param array<string, string> $server */
    private function request(string $method, string $uri, array $server = [], ?string $content = null): Response
    {
        $request = Request::create($uri, $method, server: $server, content: $content);
        $response = $this->httpKernel->handle($request);
        $this->terminableKernel?->terminate($request, $response);

        return $response;
    }

    private function apiLogin(string $email, string $password): string
    {
        $response = $this->request(
            'POST',
            '/api/login',
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{token: string} $data */
        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $data['token'];
    }

    private function createUser(string $email, string $plainPassword): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->em->persist($user);

        return $user;
    }
}
