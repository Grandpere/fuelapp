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
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
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
use ZipArchive;

final class UploadImportControllerTest extends KernelTestCase
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

        $this->resetDatabase();
        $this->cleanupImportStorage();
        $this->asyncTransport->reset();
    }

    public function testAnonymousUserGets401OnUploadEndpoint(): void
    {
        $response = $this->request('POST', '/api/imports');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testInvalidMimeTypeIsRejected(): void
    {
        $email = 'import.mime@example.com';
        $password = 'test1234';
        $this->createUser($email, $password);
        $this->em->flush();

        $token = $this->apiLogin($email, $password);
        $upload = $this->createUploadedFile('not-allowed.txt', 'hello', 'text/plain');

        $response = $this->request(
            'POST',
            '/api/imports',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
            ['file' => $upload],
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        /** @var array{message: string, errors: list<array{field: string, message: string}>} $payload */
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('Validation failed.', $payload['message']);
        self::assertNotEmpty($payload['errors']);
        self::assertSame('file', $payload['errors'][0]['field']);
    }

    public function testValidUploadCreatesQueuedImportJob(): void
    {
        $email = 'import.success@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);
        $this->em->flush();

        $token = $this->apiLogin($email, $password);
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6p9x8AAAAASUVORK5CYII=',
            true,
        );
        if (!is_string($png)) {
            throw new RuntimeException('Unable to build PNG fixture.');
        }
        $upload = $this->createUploadedFile('ticket.png', $png, 'image/png');

        $response = $this->request(
            'POST',
            '/api/imports',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
            ['file' => $upload],
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{id: string, status: string, createdAt: string} $payload */
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(ImportJobStatus::QUEUED->value, $payload['status']);

        $saved = $this->em->find(ImportJobEntity::class, $payload['id']);
        self::assertInstanceOf(ImportJobEntity::class, $saved);
        self::assertSame($user->getId()->toRfc4122(), $saved->getOwner()->getId()->toRfc4122());
        self::assertSame(ImportJobStatus::QUEUED, $saved->getStatus());
        self::assertSame('local', $saved->getStorage());
        self::assertNotSame('', trim($saved->getFilePath()));
        self::assertCount(1, $this->asyncTransport->getSent());
    }

    public function testUploadRejectsMimeExtensionMismatch(): void
    {
        $email = 'import.mime.extension.mismatch@example.com';
        $password = 'test1234';
        $this->createUser($email, $password);
        $this->em->flush();

        $token = $this->apiLogin($email, $password);
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6p9x8AAAAASUVORK5CYII=',
            true,
        );
        if (!is_string($png)) {
            throw new RuntimeException('Unable to build PNG fixture.');
        }
        $mismatched = $this->createUploadedFile('ticket.pdf', $png, 'image/png');

        $response = $this->request(
            'POST',
            '/api/imports',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
            ['file' => $mismatched],
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('does not match detected content type', (string) $response->getContent());
    }

    public function testBulkUploadWithMultipleFilesReturnsAcceptedAndRejectedSummary(): void
    {
        $email = 'import.bulk.summary@example.com';
        $password = 'test1234';
        $user = $this->createUser($email, $password);
        $this->em->flush();

        $token = $this->apiLogin($email, $password);
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6p9x8AAAAASUVORK5CYII=',
            true,
        );
        if (!is_string($png)) {
            throw new RuntimeException('Unable to build PNG fixture.');
        }

        $valid = $this->createUploadedFile('valid.png', $png, 'image/png');
        $invalid = $this->createUploadedFile('invalid.txt', 'hello', 'text/plain');

        $response = $this->request(
            'POST',
            '/api/imports/bulk',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
            ['files' => [$valid, $invalid]],
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        /** @var array{
         *   acceptedCount:int,
         *   rejectedCount:int,
         *   accepted:list<array{id:string,status:string,filename:string,source:string}>,
         *   rejected:list<array{filename:string,reason:string,source:string}>
         * } $payload
         */
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(1, $payload['acceptedCount']);
        self::assertSame(1, $payload['rejectedCount']);
        self::assertSame('valid.png', $payload['accepted'][0]['filename'] ?? null);
        self::assertSame('invalid.txt', $payload['rejected'][0]['filename'] ?? null);
        self::assertStringContainsString('Unsupported file type', $payload['rejected'][0]['reason']);

        $saved = $this->em->getRepository(ImportJobEntity::class)->findBy(['owner' => $user]);
        self::assertCount(1, $saved);
        self::assertCount(1, $this->asyncTransport->getSent());
    }

    public function testBulkUploadWithZipCreatesOneJobPerSupportedEntry(): void
    {
        $email = 'import.bulk.zip@example.com';
        $password = 'test1234';
        $this->createUser($email, $password);
        $this->em->flush();

        $token = $this->apiLogin($email, $password);
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6p9x8AAAAASUVORK5CYII=',
            true,
        );
        if (!is_string($png)) {
            throw new RuntimeException('Unable to build PNG fixture.');
        }

        $zip = $this->createUploadedZipFile('receipts.zip', [
            'receipt-a.png' => $png,
            'receipt-b.png' => $png,
            'notes.txt' => 'ignored',
            '__MACOSX/._receipt-a.png' => 'mac-fork',
            '__MACOSX/.DS_Store' => 'mac-index',
        ]);

        $response = $this->request(
            'POST',
            '/api/imports/bulk',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
            ['files' => [$zip]],
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        /** @var array{
         *   acceptedCount:int,
         *   rejectedCount:int,
         *   accepted:list<array{id:string,status:string,filename:string,source:string}>,
         *   rejected:list<array{filename:string,reason:string,source:string}>
         * } $payload
         */
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $payload['acceptedCount']);
        self::assertSame(1, $payload['rejectedCount']);
        self::assertSame('notes.txt', $payload['rejected'][0]['filename'] ?? null);
        self::assertCount(2, $this->asyncTransport->getSent());
    }

    public function testBulkZipRejectsPathTraversalEntries(): void
    {
        $email = 'import.bulk.zip.path.traversal@example.com';
        $password = 'test1234';
        $this->createUser($email, $password);
        $this->em->flush();

        $token = $this->apiLogin($email, $password);
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6p9x8AAAAASUVORK5CYII=',
            true,
        );
        if (!is_string($png)) {
            throw new RuntimeException('Unable to build PNG fixture.');
        }

        $zip = $this->createUploadedZipFile('dangerous.zip', [
            '../escape.png' => $png,
            'ok.png' => $png,
        ]);

        $response = $this->request(
            'POST',
            '/api/imports/bulk',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
            ['files' => [$zip]],
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        /** @var array{
         *   acceptedCount:int,
         *   rejectedCount:int,
         *   accepted:list<array{id:string,status:string,filename:string,source:string}>,
         *   rejected:list<array{filename:string,reason:string,source:string}>
         * } $payload
         */
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $payload['acceptedCount']);
        self::assertSame(1, $payload['rejectedCount']);
        self::assertStringContainsString('ZIP entry path is not allowed', $payload['rejected'][0]['reason'] ?? '');
    }

    public function testBulkZipRejectsOversizedEntryBeforeProcessing(): void
    {
        $email = 'import.bulk.zip.oversized@example.com';
        $password = 'test1234';
        $this->createUser($email, $password);
        $this->em->flush();

        $token = $this->apiLogin($email, $password);
        $oversizedPng = str_repeat('A', 8_600_000);
        $zip = $this->createUploadedZipFile('oversized.zip', [
            'too-big.png' => $oversizedPng,
        ]);

        $response = $this->request(
            'POST',
            '/api/imports/bulk',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
            ['files' => [$zip]],
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('File is too large. Current import limits: 8 MB for images, 1 MB for PDF.', (string) $response->getContent());
        self::assertCount(0, $this->asyncTransport->getSent());
    }

    public function testUploadEndpointRateLimitReturns429AfterTooManyAttempts(): void
    {
        $email = sprintf('import.upload.rate.%s@example.com', uniqid('', true));
        $password = 'test1234';
        $this->createUser($email, $password);
        $this->em->flush();

        $token = $this->apiLogin($email, $password);
        for ($attempt = 1; $attempt <= 20; ++$attempt) {
            $response = $this->request(
                'POST',
                '/api/imports',
                ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
            );
            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        }

        $limited = $this->request(
            'POST',
            '/api/imports',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $limited->getStatusCode());
        self::assertStringContainsString('Too many upload attempts', (string) $limited->getContent());
        self::assertNotNull($limited->headers->get('Retry-After'));
    }

    public function testBulkUploadEndpointRateLimitReturns429AfterTooManyAttempts(): void
    {
        $email = sprintf('import.bulk.rate.%s@example.com', uniqid('', true));
        $password = 'test1234';
        $this->createUser($email, $password);
        $this->em->flush();

        $token = $this->apiLogin($email, $password);
        for ($attempt = 1; $attempt <= 10; ++$attempt) {
            $response = $this->request(
                'POST',
                '/api/imports/bulk',
                ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
            );
            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        }

        $limited = $this->request(
            'POST',
            '/api/imports/bulk',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $limited->getStatusCode());
        self::assertStringContainsString('Too many bulk upload attempts', (string) $limited->getContent());
        self::assertNotNull($limited->headers->get('Retry-After'));
    }

    /**
     * @param array<string, string> $server
     * @param array<string, mixed>  $files
     */
    private function request(string $method, string $uri, array $server = [], array $files = [], ?string $content = null): Response
    {
        $request = Request::create($uri, $method, files: $files, server: $server, content: $content);
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
            [],
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

    private function createUploadedFile(string $name, string $content, string $mimeType): UploadedFile
    {
        $path = sys_get_temp_dir().'/fuelapp-import-upload-'.uniqid('', true);
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, $mimeType, null, true);
    }

    /**
     * @param array<string, string> $entries
     */
    private function createUploadedZipFile(string $name, array $entries): UploadedFile
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive extension is required for this test.');
        }

        $path = sys_get_temp_dir().'/fuelapp-import-upload-'.uniqid('', true).'.zip';
        $zip = new ZipArchive();
        if (true !== $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw new RuntimeException('Unable to create zip fixture.');
        }

        foreach ($entries as $entryName => $content) {
            $zip->addFromString($entryName, $content);
        }
        $zip->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }

    private function resetDatabase(): void
    {
        $this->em->getConnection()->executeStatement('TRUNCATE TABLE import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    private function cleanupImportStorage(): void
    {
        $filesystem = new Filesystem();
        if ($filesystem->exists($this->importStorageDir)) {
            $filesystem->remove($this->importStorageDir);
        }
    }
}
