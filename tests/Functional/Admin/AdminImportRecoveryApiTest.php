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

namespace App\Tests\Functional\Admin;

use App\Import\Application\Repository\ImportJobRepository;
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

final class AdminImportRecoveryApiTest extends KernelTestCase
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

        $importJobRepository = $container->get(ImportJobRepository::class);
        if (!$importJobRepository instanceof ImportJobRepository) {
            throw new RuntimeException('ImportJobRepository service is invalid.');
        }
        $this->importJobRepository = $importJobRepository;

        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testAdminCanRetryFailedImportOwnedByAnotherUser(): void
    {
        $owner = $this->createUser('import.owner.retry@example.com', 'test1234', ['ROLE_USER']);
        $this->createUser('import.admin.retry@example.com', 'test1234', ['ROLE_ADMIN']);
        $this->em->flush();

        $job = ImportJob::createQueued(
            $owner->getId()->toRfc4122(),
            'local',
            '2026/02/21/retry.pdf',
            'retry.pdf',
            'application/pdf',
            1024,
            str_repeat('a', 64),
        );
        $job->markFailed('ocr_provider_permanent: timeout');
        $this->importJobRepository->save($job);

        $token = $this->apiLogin('import.admin.retry@example.com', 'test1234');
        $response = $this->request(
            'POST',
            '/api/admin/imports/'.$job->id()->toString().'/retry',
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/ld+json',
            ],
            json_encode([], JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{status: string} $payload */
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('queued', $payload['status']);

        $updated = $this->importJobRepository->getForSystem($job->id()->toString());
        self::assertNotNull($updated);
        self::assertSame('queued', $updated->status()->value);
    }

    public function testAdminCanFinalizeNeedsReviewAndReceiptKeepsOriginalOwner(): void
    {
        $owner = $this->createUser('import.owner.finalize@example.com', 'test1234', ['ROLE_USER']);
        $admin = $this->createUser('import.admin.finalize@example.com', 'test1234', ['ROLE_ADMIN']);
        $this->em->flush();

        $job = ImportJob::createQueued(
            $owner->getId()->toRfc4122(),
            'local',
            '2026/02/21/finalize.pdf',
            'finalize.pdf',
            'application/pdf',
            1024,
            str_repeat('b', 64),
        );
        $job->markNeedsReview(json_encode([
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

        $token = $this->apiLogin('import.admin.finalize@example.com', 'test1234');
        $response = $this->request(
            'POST',
            '/api/admin/imports/'.$job->id()->toString().'/finalize',
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/ld+json',
            ],
            json_encode([], JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{status: string, finalizedReceiptId: ?string} $payload */
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('processed', $payload['status']);
        self::assertNotNull($payload['finalizedReceiptId']);

        $receiptOwnerId = $this->em->getConnection()->fetchOne(
            'SELECT owner_id FROM receipts WHERE id = :id',
            ['id' => $payload['finalizedReceiptId']],
        );
        self::assertSame($owner->getId()->toRfc4122(), $receiptOwnerId);
        self::assertNotSame($admin->getId()->toRfc4122(), $receiptOwnerId);
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

    /** @param list<string> $roles */
    private function createUser(string $email, string $password, array $roles): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->em->persist($user);

        return $user;
    }
}
