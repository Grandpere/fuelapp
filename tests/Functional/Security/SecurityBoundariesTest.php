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

namespace App\Tests\Functional\Security;

use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptLineEntity;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class SecurityBoundariesTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private HttpKernelInterface $httpKernel;
    private ?TerminableInterface $terminableKernel = null;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $kernel = static::getContainer()->get(HttpKernelInterface::class);
        if (!$kernel instanceof HttpKernelInterface) {
            throw new RuntimeException('HttpKernel service is invalid.');
        }
        $this->httpKernel = $kernel;
        $this->terminableKernel = $kernel instanceof TerminableInterface ? $kernel : null;

        $em = static::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service is invalid.');
        }
        $this->em = $em;

        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->resetDatabase();
    }

    public function testAnonymousUserIsRedirectedToLoginOnUiRoute(): void
    {
        $response = $this->request('GET', '/ui/receipts');

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringStartsWith('/ui/login', (string) $response->headers->get('Location'));
    }

    public function testAnonymousUserGets401OnApiRoute(): void
    {
        $response = $this->request('GET', '/api/receipts');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testAnonymousUserGets401OnAdminApiPrefix(): void
    {
        $response = $this->request('GET', '/api/admin/ping');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testRoleUserCannotAccessAdminApiPrefix(): void
    {
        $email = 'user.admin.blocked@example.com';
        $password = 'test1234';
        $this->createUser($email, $password);
        $this->em->flush();

        $token = $this->apiLogin($email, $password);
        $response = $this->request(
            'GET',
            '/api/admin/ping',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testRoleAdminCanPassAdminApiGate(): void
    {
        $email = 'admin.allowed@example.com';
        $password = 'test1234';
        $this->createUser($email, $password, ['ROLE_ADMIN']);
        $this->em->flush();

        $token = $this->apiLogin($email, $password);
        $response = $this->request(
            'GET',
            '/api/admin/ping',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAnonymousUserIsRedirectedOnAdminUiPrefix(): void
    {
        $response = $this->request('GET', '/ui/admin');

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringStartsWith('/ui/login', (string) $response->headers->get('Location'));
    }

    public function testAnonymousUserIsRedirectedOnAdminUiEntityPages(): void
    {
        $stationsResponse = $this->request('GET', '/ui/admin/stations');
        $vehiclesResponse = $this->request('GET', '/ui/admin/vehicles');

        self::assertSame(Response::HTTP_FOUND, $stationsResponse->getStatusCode());
        self::assertStringStartsWith('/ui/login', (string) $stationsResponse->headers->get('Location'));
        self::assertSame(Response::HTTP_FOUND, $vehiclesResponse->getStatusCode());
        self::assertStringStartsWith('/ui/login', (string) $vehiclesResponse->headers->get('Location'));
    }

    public function testAnonymousUserCanAccessApiDocs(): void
    {
        $htmlResponse = $this->request('GET', '/api/docs');
        self::assertSame(Response::HTTP_OK, $htmlResponse->getStatusCode());

        $openApiResponse = $this->request('GET', '/api/docs.jsonopenapi');
        self::assertSame(Response::HTTP_OK, $openApiResponse->getStatusCode());

        /** @var array{paths?: array<string, mixed>} $openApi */
        $openApi = json_decode((string) $openApiResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $paths = $openApi['paths'] ?? null;
        self::assertIsArray($paths);
        self::assertArrayHasKey('/api/imports', $paths);

        $importsPath = $paths['/api/imports'] ?? null;
        self::assertIsArray($importsPath);
        self::assertArrayHasKey('post', $importsPath);
    }

    public function testOidcLoginRoutesArePublic(): void
    {
        $response = $this->request('GET', '/ui/login/oidc/unknown');

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertStringStartsWith('/ui/login', (string) $response->headers->get('Location'));
    }

    public function testUserCannotAccessAnotherUsersReceiptFromApi(): void
    {
        $owner = $this->createUser('owner2@example.com', 'test1234');
        $otherUserEmail = 'other2@example.com';
        $otherUserPassword = 'test1234';
        $this->createUser($otherUserEmail, $otherUserPassword);

        $stationId = $this->createStation('Station B');
        $receiptId = $this->createReceipt($owner, $stationId);
        $this->em->flush();

        $token = $this->apiLogin($otherUserEmail, $otherUserPassword);

        $response = $this->request(
            'GET',
            sprintf('/api/receipts/%s', $receiptId->toRfc4122()),
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testUserCannotAccessAnotherUsersStationFromApi(): void
    {
        $owner = $this->createUser('owner3@example.com', 'test1234');
        $otherUserEmail = 'other3@example.com';
        $otherUserPassword = 'test1234';
        $this->createUser($otherUserEmail, $otherUserPassword);

        $stationId = $this->createStation('Station C');
        $this->createReceipt($owner, $stationId);
        $this->em->flush();

        $token = $this->apiLogin($otherUserEmail, $otherUserPassword);
        $response = $this->request(
            'GET',
            sprintf('/api/stations/%s', $stationId->toRfc4122()),
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
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

    private function resetDatabase(): void
    {
        $this->em->getConnection()->executeStatement('TRUNCATE TABLE receipt_lines, receipts, stations, users CASCADE');
    }

    /** @param list<string> $roles */
    private function createUser(string $email, string $plainPassword, array $roles = ['ROLE_USER']): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->em->persist($user);

        return $user;
    }

    private function createStation(string $name): UuidV7
    {
        $station = new StationEntity();
        $id = Uuid::v7();

        $station->setId($id);
        $station->setName($name);
        $station->setStreetName('1 Main St');
        $station->setPostalCode('75001');
        $station->setCity('Paris');
        $station->setLatitudeMicroDegrees(null);
        $station->setLongitudeMicroDegrees(null);
        $this->em->persist($station);

        return $id;
    }

    private function createReceipt(UserEntity $owner, UuidV7 $stationId): UuidV7
    {
        $receipt = new ReceiptEntity();
        $receiptId = Uuid::v7();

        $station = $this->em->getReference(StationEntity::class, $stationId->toRfc4122());

        $receipt->setId($receiptId);
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-02-20 10:00:00'));
        $receipt->setTotalCents(1796);
        $receipt->setVatAmountCents(299);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(9560);
        $line->setUnitPriceDeciCentsPerLiter(1879);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);

        $this->em->persist($receipt);

        return $receiptId;
    }
}
