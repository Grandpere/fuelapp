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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class UploadImportControllerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private HttpKernelInterface $httpKernel;
    private ?TerminableInterface $terminableKernel = null;
    private string $importStorageDir;

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

        $this->resetDatabase();
        $this->cleanupImportStorage();
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
    }

    /**
     * @param array<string, string>       $server
     * @param array<string, UploadedFile> $files
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
