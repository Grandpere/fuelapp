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

use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\Enum\ReminderRuleTriggerMode;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenanceEventEntity;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenanceReminderEntity;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenanceReminderRuleEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity;
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

final class AdminApiManagementTest extends KernelTestCase
{
    private EntityManagerInterface $em;
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

        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE maintenance_reminders, maintenance_reminder_rules, maintenance_events, maintenance_planned_costs, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testRoleUserCannotAccessAdminCollections(): void
    {
        $email = 'admin.api.user@example.com';
        $password = 'test1234';
        $this->createUser($email, $password, ['ROLE_USER']);
        $this->em->flush();

        $token = $this->apiLogin($email, $password);
        $stationsResponse = $this->request('GET', '/api/admin/stations', ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)]);
        $vehiclesResponse = $this->request('GET', '/api/admin/vehicles', ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)]);
        $maintenanceEventsResponse = $this->request('GET', '/api/admin/maintenance/events', ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)]);
        $maintenanceRemindersResponse = $this->request('GET', '/api/admin/maintenance/reminders', ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)]);

        self::assertSame(Response::HTTP_FORBIDDEN, $stationsResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $vehiclesResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $maintenanceEventsResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $maintenanceRemindersResponse->getStatusCode());
    }

    public function testAdminCanManageVehicleWithoutCreateOperation(): void
    {
        $token = $this->createAdminAndLogin('admin.vehicle@example.com');
        $vehicleOwner = $this->createUser('vehicle.owner@example.com', 'test1234', ['ROLE_USER']);
        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setOwner($vehicleOwner);
        $vehicle->setName('Peugeot 208');
        $vehicle->setPlateNumber('aa-123-bb');
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $this->em->persist($vehicle);
        $this->em->flush();

        $createResponse = $this->request(
            'POST',
            '/api/admin/vehicles',
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/ld+json',
            ],
            json_encode([
                'ownerId' => $vehicleOwner->getId()->toRfc4122(),
                'name' => 'Should Fail',
                'plateNumber' => 'NO-POST-01',
            ], JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $createResponse->getStatusCode());

        $listResponse = $this->request(
            'GET',
            '/api/admin/vehicles?q=AA-123-BB',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_OK, $listResponse->getStatusCode());
        $decodedVehicles = json_decode((string) $listResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $listPayload = $this->extractCollectionItems($decodedVehicles);
        self::assertNotEmpty($listPayload);
        self::assertArrayHasKey('id', $listPayload[0]);
        self::assertIsString($listPayload[0]['id']);
        self::assertSame($vehicle->getId()->toRfc4122(), $listPayload[0]['id']);

        $patchResponse = $this->request(
            'PATCH',
            '/api/admin/vehicles/'.$vehicle->getId()->toRfc4122(),
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/merge-patch+json',
            ],
            json_encode([
                'ownerId' => $vehicleOwner->getId()->toRfc4122(),
                'name' => 'Peugeot 208 GT',
                'plateNumber' => 'AA-123-BB',
            ], JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_OK, $patchResponse->getStatusCode());

        $deleteResponse = $this->request(
            'DELETE',
            '/api/admin/vehicles/'.$vehicle->getId()->toRfc4122(),
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_NO_CONTENT, $deleteResponse->getStatusCode());
    }

    public function testAdminCanManageStationCrudAndFilterByCity(): void
    {
        $token = $this->createAdminAndLogin('admin.station@example.com');

        $createResponse = $this->request(
            'POST',
            '/api/admin/stations',
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/ld+json',
            ],
            json_encode([
                'name' => 'Total',
                'streetName' => '10 Rue A',
                'postalCode' => '75001',
                'city' => 'Paris',
            ], JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $createResponse->getStatusCode());

        /** @var array{id: string} $created */
        $created = json_decode((string) $createResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $id = $created['id'];

        $filterResponse = $this->request(
            'GET',
            '/api/admin/stations?city=Paris',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_OK, $filterResponse->getStatusCode());
        $decodedStations = json_decode((string) $filterResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $stations = $this->extractCollectionItems($decodedStations);
        self::assertNotEmpty($stations);
        self::assertArrayHasKey('id', $stations[0]);
        self::assertIsString($stations[0]['id']);
        self::assertSame($id, $stations[0]['id']);

        $patchResponse = $this->request(
            'PATCH',
            '/api/admin/stations/'.$id,
            [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/merge-patch+json',
            ],
            json_encode([
                'name' => 'Total Access',
                'streetName' => '10 Rue A',
                'postalCode' => '75001',
                'city' => 'Paris',
            ], JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_OK, $patchResponse->getStatusCode());
    }

    public function testAdminCanQueryMaintenanceEventsAndRemindersWithFilters(): void
    {
        $token = $this->createAdminAndLogin('admin.maintenance@example.com');
        $owner = $this->createUser('maintenance.owner@example.com', 'test1234', ['ROLE_USER']);
        $this->em->flush();

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setOwner($owner);
        $vehicle->setName('Maintenance Car');
        $vehicle->setPlateNumber('MM-001-AA');
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $this->em->persist($vehicle);

        $event = new MaintenanceEventEntity();
        $event->setId(Uuid::v7());
        $event->setOwner($owner);
        $event->setVehicle($vehicle);
        $event->setEventType(MaintenanceEventType::SERVICE);
        $event->setOccurredAt(new DateTimeImmutable('2026-03-02 09:30:00'));
        $event->setDescription('Admin seeded maintenance event');
        $event->setOdometerKilometers(124000);
        $event->setTotalCostCents(18990);
        $event->setCurrencyCode('EUR');
        $event->setCreatedAt(new DateTimeImmutable('2026-03-02 09:30:00'));
        $event->setUpdatedAt(new DateTimeImmutable('2026-03-02 09:30:00'));
        $this->em->persist($event);

        $rule = new MaintenanceReminderRuleEntity();
        $rule->setId(Uuid::v7());
        $rule->setOwner($owner);
        $rule->setVehicle($vehicle);
        $rule->setName('Oil change rule');
        $rule->setTriggerMode(ReminderRuleTriggerMode::DATE);
        $rule->setEventType(MaintenanceEventType::SERVICE);
        $rule->setIntervalDays(365);
        $rule->setIntervalKilometers(null);
        $rule->setCreatedAt(new DateTimeImmutable('2026-03-02 10:00:00'));
        $rule->setUpdatedAt(new DateTimeImmutable('2026-03-02 10:00:00'));
        $this->em->persist($rule);

        $reminder = new MaintenanceReminderEntity();
        $reminder->setId(Uuid::v7());
        $reminder->setOwner($owner);
        $reminder->setVehicle($vehicle);
        $reminder->setRule($rule);
        $reminder->setDedupKey(hash('sha256', 'admin-maintenance-reminder'));
        $reminder->setDueAtDate(new DateTimeImmutable('2026-03-05 00:00:00'));
        $reminder->setDueAtOdometerKilometers(null);
        $reminder->setDueByDate(true);
        $reminder->setDueByOdometer(false);
        $reminder->setCreatedAt(new DateTimeImmutable('2026-03-03 08:45:00'));
        $this->em->persist($reminder);
        $this->em->flush();

        $ownerId = $owner->getId()->toRfc4122();
        $vehicleId = $vehicle->getId()->toRfc4122();

        $eventsResponse = $this->request(
            'GET',
            '/api/admin/maintenance/events?ownerId='.$ownerId.'&vehicleId='.$vehicleId.'&eventType=service',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_OK, $eventsResponse->getStatusCode());
        $decodedEvents = json_decode((string) $eventsResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $events = $this->extractCollectionItems($decodedEvents);
        self::assertNotEmpty($events);
        self::assertSame('service', $events[0]['eventType'] ?? null);
        self::assertSame($ownerId, $events[0]['ownerId'] ?? null);

        $remindersResponse = $this->request(
            'GET',
            '/api/admin/maintenance/reminders?ownerId='.$ownerId.'&vehicleId='.$vehicleId.'&dueBy=date&dueFrom=2026-03-01&dueTo=2026-03-31',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_OK, $remindersResponse->getStatusCode());
        $decodedReminders = json_decode((string) $remindersResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $reminders = $this->extractCollectionItems($decodedReminders);
        self::assertNotEmpty($reminders);
        self::assertSame($ownerId, $reminders[0]['ownerId'] ?? null);
        self::assertTrue((bool) ($reminders[0]['dueByDate'] ?? false));
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

    private function createAdminAndLogin(string $email): string
    {
        $password = 'test1234';
        $this->createUser($email, $password, ['ROLE_ADMIN']);
        $this->em->flush();

        return $this->apiLogin($email, $password);
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
