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

namespace App\Tests\Functional\Maintenance;

use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Application\Repository\VehicleRepository;
use App\Vehicle\Domain\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class MaintenanceEventApiTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private VehicleRepository $vehicleRepository;
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

        $vehicleRepository = $container->get(VehicleRepository::class);
        if (!$vehicleRepository instanceof VehicleRepository) {
            throw new RuntimeException('VehicleRepository service is invalid.');
        }
        $this->vehicleRepository = $vehicleRepository;

        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE maintenance_events, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testUserCanCrudOwnMaintenanceEvent(): void
    {
        $owner = $this->createUser('maintenance.owner@example.com', 'test1234', ['ROLE_USER']);
        $this->em->flush();

        $vehicle = Vehicle::create($owner->getId()->toRfc4122(), 'Clio', 'ab-123-cd');
        $this->vehicleRepository->save($vehicle);

        $token = $this->apiLogin('maintenance.owner@example.com', 'test1234');
        $createResponse = $this->request(
            'POST',
            '/api/maintenance/events',
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/ld+json',
            ],
            json_encode([
                'vehicleId' => $vehicle->id()->toString(),
                'eventType' => 'service',
                'occurredAt' => '2026-02-22T10:00:00+00:00',
                'description' => 'Annual maintenance',
                'odometerKilometers' => 102500,
                'totalCostCents' => 22990,
                'currencyCode' => 'EUR',
            ], JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $createResponse->getStatusCode());

        /** @var array{id: string} $created */
        $created = json_decode((string) $createResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $id = $created['id'];

        $collectionResponse = $this->request(
            'GET',
            '/api/maintenance/events',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_OK, $collectionResponse->getStatusCode());
        $decodedCollection = json_decode((string) $collectionResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $items = $this->extractCollectionItems($decodedCollection);
        self::assertNotEmpty($items);
        self::assertSame($id, $items[0]['id'] ?? null);

        $showResponse = $this->request(
            'GET',
            '/api/maintenance/events/'.$id,
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_OK, $showResponse->getStatusCode());

        $patchResponse = $this->request(
            'PATCH',
            '/api/maintenance/events/'.$id,
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/merge-patch+json',
            ],
            json_encode([
                'vehicleId' => $vehicle->id()->toString(),
                'eventType' => 'repair',
                'occurredAt' => '2026-02-23T10:00:00+00:00',
                'description' => 'Brake pads',
                'odometerKilometers' => 103000,
                'totalCostCents' => 34990,
                'currencyCode' => 'EUR',
            ], JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_OK, $patchResponse->getStatusCode());
        /** @var array{eventType?: string, totalCostCents?: int} $patched */
        $patched = json_decode((string) $patchResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('repair', $patched['eventType'] ?? null);
        self::assertSame(34990, $patched['totalCostCents'] ?? null);

        $deleteResponse = $this->request(
            'DELETE',
            '/api/maintenance/events/'.$id,
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_NO_CONTENT, $deleteResponse->getStatusCode());

        $afterDeleteResponse = $this->request(
            'GET',
            '/api/maintenance/events/'.$id,
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_NOT_FOUND, $afterDeleteResponse->getStatusCode());
    }

    public function testValidationErrorsAreExplicit(): void
    {
        $owner = $this->createUser('maintenance.validation@example.com', 'test1234', ['ROLE_USER']);
        $this->em->flush();

        $vehicle = Vehicle::create($owner->getId()->toRfc4122(), '208', 'aa-111-bb');
        $this->vehicleRepository->save($vehicle);

        $token = $this->apiLogin('maintenance.validation@example.com', 'test1234');
        $response = $this->request(
            'POST',
            '/api/maintenance/events',
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/ld+json',
            ],
            json_encode([
                'vehicleId' => $vehicle->id()->toString(),
                'eventType' => 'service',
                'occurredAt' => '2026-02-22T10:00:00+00:00',
                'totalCostCents' => -1,
                'currencyCode' => 'EURO',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $payload = (string) $response->getContent();
        self::assertStringContainsString('totalCostCents', $payload);
        self::assertStringContainsString('currencyCode', $payload);
    }

    public function testUserCannotCreateOrReadMaintenanceEventForAnotherUsersVehicle(): void
    {
        $ownerA = $this->createUser('maintenance.owner.a@example.com', 'test1234', ['ROLE_USER']);
        $ownerB = $this->createUser('maintenance.owner.b@example.com', 'test1234', ['ROLE_USER']);
        $this->em->flush();

        $vehicleA = Vehicle::create($ownerA->getId()->toRfc4122(), 'Golf', 'aa-222-bb');
        $vehicleB = Vehicle::create($ownerB->getId()->toRfc4122(), 'Polo', 'cc-333-dd');
        $this->vehicleRepository->save($vehicleA);
        $this->vehicleRepository->save($vehicleB);

        $tokenA = $this->apiLogin('maintenance.owner.a@example.com', 'test1234');
        $tokenB = $this->apiLogin('maintenance.owner.b@example.com', 'test1234');

        $createAResponse = $this->request(
            'POST',
            '/api/maintenance/events',
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $tokenA),
                'CONTENT_TYPE' => 'application/ld+json',
            ],
            json_encode([
                'vehicleId' => $vehicleA->id()->toString(),
                'eventType' => 'inspection',
                'occurredAt' => '2026-02-24T10:00:00+00:00',
                'currencyCode' => 'EUR',
            ], JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $createAResponse->getStatusCode());
        /** @var array{id?: string} $created */
        $created = json_decode((string) $createAResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $eventId = $created['id'] ?? null;
        self::assertIsString($eventId);

        $forbiddenCreateResponse = $this->request(
            'POST',
            '/api/maintenance/events',
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $tokenA),
                'CONTENT_TYPE' => 'application/ld+json',
            ],
            json_encode([
                'vehicleId' => $vehicleB->id()->toString(),
                'eventType' => 'service',
                'occurredAt' => '2026-02-24T11:00:00+00:00',
                'currencyCode' => 'EUR',
            ], JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_NOT_FOUND, $forbiddenCreateResponse->getStatusCode());

        $crossReadResponse = $this->request(
            'GET',
            '/api/maintenance/events/'.$eventId,
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $tokenB)],
        );
        self::assertSame(Response::HTTP_NOT_FOUND, $crossReadResponse->getStatusCode());
    }

    /** @param array<string, string> $server */
    private function request(string $method, string $uri, array $server = [], ?string $content = null): Response
    {
        $request = Request::create($uri, $method, server: $server, content: $content);
        $response = $this->httpKernel->handle($request);
        $this->terminableKernel?->terminate($request, $response);

        return $response;
    }

    /** @return list<array<string, mixed>> */
    private function extractCollectionItems(mixed $decoded): array
    {
        if (!is_array($decoded)) {
            return [];
        }

        $items = $decoded;
        if (array_key_exists('member', $decoded) && is_array($decoded['member'])) {
            $items = $decoded['member'];
        }

        $result = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $normalized = [];
                foreach ($item as $key => $value) {
                    if (is_string($key)) {
                        $normalized[$key] = $value;
                    }
                }

                $result[] = $normalized;
            }
        }

        return $result;
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
