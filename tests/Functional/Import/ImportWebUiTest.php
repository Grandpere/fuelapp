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

use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Infrastructure\Persistence\Doctrine\Entity\ImportJobEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class ImportWebUiTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private HttpKernelInterface $httpKernel;
    private ?TerminableInterface $terminableKernel = null;
    private string $importStorageDir;
    private InMemoryTransport $asyncTransport;

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

        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $parameterBag = $container->get(ParameterBagInterface::class);
        if (!$parameterBag instanceof ParameterBagInterface) {
            throw new RuntimeException('Parameter bag service is invalid.');
        }
        $importStorageDir = $parameterBag->get('app.import.storage_dir');
        if (!is_string($importStorageDir) || '' === trim($importStorageDir)) {
            throw new RuntimeException('Import storage parameter is invalid.');
        }
        $this->importStorageDir = $importStorageDir;

        $transport = $container->get('messenger.transport.async');
        if (!$transport instanceof InMemoryTransport) {
            throw new RuntimeException('Async transport is not in-memory in test env.');
        }
        $this->asyncTransport = $transport;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
        $filesystem = new Filesystem();
        if ($filesystem->exists($this->importStorageDir)) {
            $filesystem->remove($this->importStorageDir);
        }
        $this->asyncTransport->reset();
    }

    public function testUserCanUploadFromUiAndSeeQueuedImportInList(): void
    {
        $email = 'import.web.user@example.com';
        $password = 'test1234';
        $this->createUser($email, $password);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);

        $pageResponse = $this->request('GET', '/ui/imports', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $pageResponse->getStatusCode());
        $pageContent = (string) $pageResponse->getContent();
        self::assertStringContainsString('Import Receipts', $pageContent);

        preg_match('/name="_token" value="([^"]+)"/', $pageContent, $matches);
        $csrfToken = $matches[1] ?? null;
        self::assertIsString($csrfToken);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6p9x8AAAAASUVORK5CYII=',
            true,
        );
        if (!is_string($png)) {
            throw new RuntimeException('Unable to build PNG fixture.');
        }

        $uploadResponse = $this->request(
            'POST',
            '/ui/imports',
            ['_token' => $csrfToken],
            ['file' => $this->createUploadedFile('ticket.png', $png, 'image/png')],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_FOUND, $uploadResponse->getStatusCode());

        $listResponse = $this->request('GET', '/ui/imports', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $listResponse->getStatusCode());
        $listContent = (string) $listResponse->getContent();
        self::assertStringContainsString('ticket.png', $listContent);
        self::assertStringContainsString('Queued', $listContent);

        $saved = $this->em->getRepository(ImportJobEntity::class)->findOneBy(['originalFilename' => 'ticket.png']);
        self::assertInstanceOf(ImportJobEntity::class, $saved);
        self::assertCount(1, $this->asyncTransport->getSent());
    }

    public function testUserCanFinalizeNeedsReviewImportFromUi(): void
    {
        $email = 'import.web.finalize@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($user);
        $job->setStatus(ImportJobStatus::NEEDS_REVIEW);
        $job->setStorage('local');
        $job->setFilePath('2026/02/21/to-finalize.jpg');
        $job->setOriginalFilename('to-finalize.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('a', 64));
        $job->setErrorPayload(json_encode([
            'parsedDraft' => [
                'creationPayload' => [
                    'issuedAt' => '2026-02-21T10:45:00+00:00',
                    'stationName' => 'TOTAL ENERGIES',
                    'stationStreetName' => '1 Rue de Rivoli',
                    'stationPostalCode' => '75001',
                    'stationCity' => 'Paris',
                    'lines' => [[
                        'fuelType' => 'diesel',
                        'quantityMilliLiters' => 40000,
                        'unitPriceDeciCentsPerLiter' => 1879,
                        'vatRatePercent' => 20,
                    ]],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-02-21 10:46:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-02-21 10:46:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-03-21 10:46:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);

        $listResponse = $this->request('GET', '/ui/imports', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $listResponse->getStatusCode());
        $listContent = (string) $listResponse->getContent();
        self::assertStringContainsString('to-finalize.jpg', $listContent);

        $jobId = $job->getId()->toRfc4122();
        $csrfToken = $this->extractFinalizeCsrfToken($listContent, $jobId);

        $finalizeResponse = $this->request(
            'POST',
            '/ui/imports/'.$jobId.'/finalize',
            ['_token' => $csrfToken],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_FOUND, $finalizeResponse->getStatusCode());

        $this->em->clear();
        $updated = $this->em->find(ImportJobEntity::class, $jobId);
        self::assertInstanceOf(ImportJobEntity::class, $updated);
        self::assertSame(ImportJobStatus::PROCESSED, $updated->getStatus());
        self::assertStringContainsString('finalizedReceiptId', (string) $updated->getErrorPayload());

        $receiptCount = $this->em->getRepository(ReceiptEntity::class)->count([]);
        self::assertSame(1, $receiptCount);
    }

    /**
     * @param array<string, string|int|float|bool|null> $parameters
     * @param array<string, UploadedFile>               $files
     * @param array<string, string>                     $cookies
     */
    private function request(string $method, string $uri, array $parameters = [], array $files = [], array $cookies = []): Response
    {
        $request = Request::create($uri, $method, $parameters, $cookies, $files);
        $response = $this->httpKernel->handle($request);
        $this->terminableKernel?->terminate($request, $response);

        return $response;
    }

    /** @return array<string, string> */
    private function loginWithUiForm(string $email, string $password): array
    {
        $loginPageResponse = $this->request('GET', '/ui/login');
        self::assertSame(Response::HTTP_OK, $loginPageResponse->getStatusCode());

        $sessionCookie = $this->extractSessionCookie($loginPageResponse);
        self::assertNotEmpty($sessionCookie);

        $content = (string) $loginPageResponse->getContent();
        preg_match('/name="_csrf_token" value="([^"]+)"/', $content, $matches);
        $csrfToken = $matches[1] ?? null;
        self::assertIsString($csrfToken);

        $loginResponse = $this->request(
            'POST',
            '/ui/login',
            [
                'email' => $email,
                'password' => $password,
                '_csrf_token' => $csrfToken,
            ],
            [],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_FOUND, $loginResponse->getStatusCode());

        return $this->extractSessionCookie($loginResponse) ?: $sessionCookie;
    }

    /** @return array<string, string> */
    private function extractSessionCookie(Response $response): array
    {
        $cookies = $response->headers->getCookies();
        foreach ($cookies as $cookie) {
            if (str_starts_with($cookie->getName(), 'MOCKSESSID') || str_starts_with($cookie->getName(), 'PHPSESSID')) {
                return [$cookie->getName() => (string) $cookie->getValue()];
            }
        }

        return [];
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

    private function createUploadedFile(string $name, string $content, string $mimeType): UploadedFile
    {
        $path = sys_get_temp_dir().'/fuelapp-import-upload-ui-'.uniqid('', true);
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, $mimeType, null, true);
    }

    private function extractFinalizeCsrfToken(string $content, string $jobId): string
    {
        $pattern = '#/ui/imports/'.preg_quote($jobId, '#').'/finalize.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
    }
}
