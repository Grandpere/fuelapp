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

use App\Admin\Infrastructure\Persistence\Doctrine\Entity\AdminAuditLogEntity;
use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Infrastructure\Persistence\Doctrine\Entity\ImportJobEntity;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\Enum\ReminderRuleTriggerMode;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenanceEventEntity;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenanceReminderEntity;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenanceReminderRuleEntity;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
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

final class AdminBackofficeUiTest extends KernelTestCase
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

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE admin_audit_logs, maintenance_reminders, maintenance_reminder_rules, maintenance_events, maintenance_planned_costs, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testRoleUserCannotAccessAdminUiPagesWhenAuthenticated(): void
    {
        $email = 'ui.user.blocked@example.com';
        $password = 'test1234';
        $this->createUser($email, $password, ['ROLE_USER']);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);

        $dashboardResponse = $this->request('GET', '/ui/admin', [], [], $sessionCookie);
        $stationsResponse = $this->request('GET', '/ui/admin/stations', [], [], $sessionCookie);
        $vehiclesResponse = $this->request('GET', '/ui/admin/vehicles', [], [], $sessionCookie);
        $maintenanceEventsResponse = $this->request('GET', '/ui/admin/maintenance/events', [], [], $sessionCookie);
        $maintenanceRemindersResponse = $this->request('GET', '/ui/admin/maintenance/reminders', [], [], $sessionCookie);
        $importsResponse = $this->request('GET', '/ui/admin/imports', [], [], $sessionCookie);
        $auditResponse = $this->request('GET', '/ui/admin/audit-logs', [], [], $sessionCookie);

        self::assertSame(Response::HTTP_FORBIDDEN, $dashboardResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $stationsResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $vehiclesResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $maintenanceEventsResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $maintenanceRemindersResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $importsResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $auditResponse->getStatusCode());
    }

    public function testAdminCanAccessBackofficePagesAndSeeSeededData(): void
    {
        $adminEmail = 'ui.admin.allowed@example.com';
        $adminPassword = 'test1234';
        $admin = $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);

        $owner = $this->createUser('ui.owner@example.com', 'test1234', ['ROLE_USER']);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('UI Station');
        $station->setStreetName('10 Rue UI');
        $station->setPostalCode('75001');
        $station->setCity('Paris');
        $station->setLatitudeMicroDegrees(48856600);
        $station->setLongitudeMicroDegrees(2352200);
        $this->em->persist($station);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('UI Vehicle');
        $vehicle->setPlateNumber('AA-123-UI');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-02-22 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-02-22 10:00:00'));
        $this->em->persist($vehicle);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($owner);
        $job->setStatus(ImportJobStatus::NEEDS_REVIEW);
        $job->setStorage('local');
        $job->setFilePath('2026/02/22/ui-import.pdf');
        $job->setOriginalFilename('ui-import.pdf');
        $job->setMimeType('application/pdf');
        $job->setFileSizeBytes(1024);
        $job->setFileChecksumSha256(str_repeat('f', 64));
        $job->setCreatedAt(new DateTimeImmutable('2026-02-22 09:00:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-02-22 09:00:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-03-22 09:00:00'));
        $this->em->persist($job);

        $maintenanceEvent = new MaintenanceEventEntity();
        $maintenanceEvent->setId(Uuid::v7());
        $maintenanceEvent->setOwner($owner);
        $maintenanceEvent->setVehicle($vehicle);
        $maintenanceEvent->setEventType(MaintenanceEventType::SERVICE);
        $maintenanceEvent->setOccurredAt(new DateTimeImmutable('2026-02-22 08:30:00'));
        $maintenanceEvent->setDescription('UI maintenance event');
        $maintenanceEvent->setOdometerKilometers(120000);
        $maintenanceEvent->setTotalCostCents(22000);
        $maintenanceEvent->setCurrencyCode('EUR');
        $maintenanceEvent->setCreatedAt(new DateTimeImmutable('2026-02-22 08:30:00'));
        $maintenanceEvent->setUpdatedAt(new DateTimeImmutable('2026-02-22 08:30:00'));
        $this->em->persist($maintenanceEvent);

        $rule = new MaintenanceReminderRuleEntity();
        $rule->setId(Uuid::v7());
        $rule->setOwner($owner);
        $rule->setVehicle($vehicle);
        $rule->setName('UI rule');
        $rule->setTriggerMode(ReminderRuleTriggerMode::DATE);
        $rule->setEventType(MaintenanceEventType::SERVICE);
        $rule->setIntervalDays(365);
        $rule->setIntervalKilometers(null);
        $rule->setCreatedAt(new DateTimeImmutable('2026-02-22 08:40:00'));
        $rule->setUpdatedAt(new DateTimeImmutable('2026-02-22 08:40:00'));
        $this->em->persist($rule);

        $reminder = new MaintenanceReminderEntity();
        $reminder->setId(Uuid::v7());
        $reminder->setOwner($owner);
        $reminder->setVehicle($vehicle);
        $reminder->setRule($rule);
        $reminder->setDedupKey(hash('sha256', 'ui-admin-maintenance-reminder'));
        $reminder->setDueAtDate(new DateTimeImmutable('2026-02-25 00:00:00'));
        $reminder->setDueAtOdometerKilometers(null);
        $reminder->setDueByDate(true);
        $reminder->setDueByOdometer(false);
        $reminder->setCreatedAt(new DateTimeImmutable('2026-02-22 08:50:00'));
        $this->em->persist($reminder);

        $audit = new AdminAuditLogEntity();
        $audit->setId(Uuid::v7());
        $audit->setActorId($admin->getId());
        $audit->setActorEmail($adminEmail);
        $audit->setAction('admin.station.updated');
        $audit->setTargetType('station');
        $audit->setTargetId($station->getId()->toRfc4122());
        $audit->setDiffSummary(['city' => ['old' => 'Lyon', 'new' => 'Paris']]);
        $audit->setMetadata(['source' => 'ui']);
        $audit->setCorrelationId('corr-ui-admin-001');
        $audit->setCreatedAt(new DateTimeImmutable('2026-02-22 11:00:00'));
        $this->em->persist($audit);

        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);

        $dashboardResponse = $this->request('GET', '/ui/admin', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $dashboardResponse->getStatusCode());
        self::assertStringContainsString('Back-office', (string) $dashboardResponse->getContent());

        $stationsResponse = $this->request('GET', '/ui/admin/stations', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $stationsResponse->getStatusCode());
        self::assertStringContainsString('UI Station', (string) $stationsResponse->getContent());

        $vehiclesResponse = $this->request('GET', '/ui/admin/vehicles', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $vehiclesResponse->getStatusCode());
        self::assertStringContainsString('UI Vehicle', (string) $vehiclesResponse->getContent());
        self::assertStringContainsString('AA-123-UI', (string) $vehiclesResponse->getContent());

        $maintenanceEventsResponse = $this->request('GET', '/ui/admin/maintenance/events', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $maintenanceEventsResponse->getStatusCode());
        self::assertStringContainsString('Maintenance Events', (string) $maintenanceEventsResponse->getContent());
        self::assertStringContainsString('UI maintenance event', (string) $maintenanceEventsResponse->getContent());

        $maintenanceRemindersResponse = $this->request('GET', '/ui/admin/maintenance/reminders', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $maintenanceRemindersResponse->getStatusCode());
        self::assertStringContainsString('Maintenance Reminders', (string) $maintenanceRemindersResponse->getContent());
        self::assertStringContainsString('UI rule', (string) $maintenanceRemindersResponse->getContent());

        $importsResponse = $this->request('GET', '/ui/admin/imports', ['status' => 'needs_review'], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $importsResponse->getStatusCode());
        self::assertStringContainsString('ui-import.pdf', (string) $importsResponse->getContent());
        self::assertStringContainsString('needs_review', (string) $importsResponse->getContent());

        $auditResponse = $this->request('GET', '/ui/admin/audit-logs', ['action' => 'admin.station.updated'], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $auditResponse->getStatusCode());
        self::assertStringContainsString('admin.station.updated', (string) $auditResponse->getContent());
        self::assertStringContainsString('corr-ui-admin-001', (string) $auditResponse->getContent());
    }

    public function testAdminCanEditAndDeleteVehicleFromBackofficeUiWithoutCreatePage(): void
    {
        $adminEmail = 'ui.admin.vehicle.write@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.vehicle.owner@example.com', 'test1234', ['ROLE_USER']);
        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setOwner($owner);
        $vehicle->setName('Backoffice Vehicle');
        $vehicle->setPlateNumber('BO-100-AA');
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $this->em->persist($vehicle);
        $this->em->flush();

        $ownerId = $owner->getId()->toRfc4122();
        $vehicleId = $vehicle->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);

        $newPage = $this->request('GET', '/ui/admin/vehicles/new', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_NOT_FOUND, $newPage->getStatusCode());

        $listResponse = $this->request('GET', '/ui/admin/vehicles', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $listResponse->getStatusCode());
        self::assertStringNotContainsString('Create vehicle', (string) $listResponse->getContent());
        self::assertStringContainsString('Backoffice Vehicle', (string) $listResponse->getContent());

        $editPage = $this->request('GET', '/ui/admin/vehicles/'.$vehicleId.'/edit', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $editPage->getStatusCode());
        $editCsrf = $this->extractFormCsrf((string) $editPage->getContent());

        $editResponse = $this->request(
            'POST',
            '/ui/admin/vehicles/'.$vehicleId.'/edit',
            [
                'ownerId' => $ownerId,
                'name' => 'Backoffice Vehicle Updated',
                'plateNumber' => 'BO-200-BB',
                '_token' => $editCsrf,
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $editResponse->getStatusCode());

        $updatedList = $this->request('GET', '/ui/admin/vehicles', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $updatedList->getStatusCode());
        self::assertStringContainsString('Backoffice Vehicle Updated', (string) $updatedList->getContent());
        $deleteToken = $this->extractDeleteCsrfForVehicle((string) $updatedList->getContent(), $vehicleId);

        $deleteResponse = $this->request(
            'POST',
            '/ui/admin/vehicles/'.$vehicleId.'/delete',
            [
                '_token' => $deleteToken,
            ],
            [],
            $sessionCookie,
        );
        self::assertContains($deleteResponse->getStatusCode(), [Response::HTTP_FOUND, Response::HTTP_SEE_OTHER]);

        $afterDelete = $this->request('GET', '/ui/admin/vehicles', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $afterDelete->getStatusCode());
        self::assertStringNotContainsString('BO-200-BB', (string) $afterDelete->getContent());
    }

    public function testAdminCanDeleteImportJobFromBackofficeUi(): void
    {
        $adminEmail = 'ui.admin.import.delete@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.import.owner@example.com', 'test1234', ['ROLE_USER']);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($owner);
        $job->setStatus(ImportJobStatus::FAILED);
        $job->setStorage('local');
        $job->setFilePath('2026/03/15/failed-import.pdf');
        $job->setOriginalFilename('failed-import.pdf');
        $job->setMimeType('application/pdf');
        $job->setFileSizeBytes(1024);
        $job->setFileChecksumSha256(str_repeat('f', 64));
        $job->setErrorPayload('{"error":"failed"}');
        $job->setCreatedAt(new DateTimeImmutable('2026-03-15 09:00:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-15 09:00:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-15 09:00:00'));
        $this->em->persist($job);
        $this->em->flush();

        $jobId = $job->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);

        $listResponse = $this->request('GET', '/ui/admin/imports', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $listResponse->getStatusCode());
        self::assertStringContainsString('failed-import.pdf', (string) $listResponse->getContent());
        $deleteToken = $this->extractDeleteCsrfForImport((string) $listResponse->getContent(), $jobId);

        $deleteResponse = $this->request(
            'POST',
            '/ui/admin/imports/'.$jobId.'/delete',
            [
                '_token' => $deleteToken,
            ],
            [],
            $sessionCookie,
        );
        self::assertContains($deleteResponse->getStatusCode(), [Response::HTTP_FOUND, Response::HTTP_SEE_OTHER]);
        self::assertSame('/ui/admin/imports', $deleteResponse->headers->get('Location'));

        $afterDelete = $this->request('GET', '/ui/admin/imports', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $afterDelete->getStatusCode());
        self::assertStringNotContainsString('failed-import.pdf', (string) $afterDelete->getContent());
        self::assertNull($this->em->find(ImportJobEntity::class, $jobId));
    }

    public function testAdminCanEditAndDeleteStationAndMaintenanceEventFromBackofficeUi(): void
    {
        $adminEmail = 'ui.admin.station.event.write@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.station.event.owner@example.com', 'test1234', ['ROLE_USER']);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Initial Station');
        $station->setStreetName('1 Old Street');
        $station->setPostalCode('75001');
        $station->setCity('Paris');
        $station->setLatitudeMicroDegrees(48856600);
        $station->setLongitudeMicroDegrees(2352200);
        $this->em->persist($station);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setOwner($owner);
        $vehicle->setName('Admin Event Vehicle');
        $vehicle->setPlateNumber('AE-101-AA');
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-02 09:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-02 09:00:00'));
        $this->em->persist($vehicle);

        $event = new MaintenanceEventEntity();
        $event->setId(Uuid::v7());
        $event->setOwner($owner);
        $event->setVehicle($vehicle);
        $event->setEventType(MaintenanceEventType::SERVICE);
        $event->setOccurredAt(new DateTimeImmutable('2026-03-02 10:00:00'));
        $event->setDescription('Initial maintenance event');
        $event->setOdometerKilometers(100000);
        $event->setTotalCostCents(20000);
        $event->setCurrencyCode('EUR');
        $event->setCreatedAt(new DateTimeImmutable('2026-03-02 10:00:00'));
        $event->setUpdatedAt(new DateTimeImmutable('2026-03-02 10:00:00'));
        $this->em->persist($event);

        $rule = new MaintenanceReminderRuleEntity();
        $rule->setId(Uuid::v7());
        $rule->setOwner($owner);
        $rule->setVehicle($vehicle);
        $rule->setName('Reminder detail rule');
        $rule->setTriggerMode(ReminderRuleTriggerMode::DATE);
        $rule->setEventType(MaintenanceEventType::SERVICE);
        $rule->setIntervalDays(365);
        $rule->setIntervalKilometers(null);
        $rule->setCreatedAt(new DateTimeImmutable('2026-03-02 10:05:00'));
        $rule->setUpdatedAt(new DateTimeImmutable('2026-03-02 10:05:00'));
        $this->em->persist($rule);

        $reminder = new MaintenanceReminderEntity();
        $reminder->setId(Uuid::v7());
        $reminder->setOwner($owner);
        $reminder->setVehicle($vehicle);
        $reminder->setRule($rule);
        $reminder->setDedupKey(hash('sha256', 'admin-reminder-detail'));
        $reminder->setDueAtDate(new DateTimeImmutable('2026-03-30 00:00:00'));
        $reminder->setDueAtOdometerKilometers(null);
        $reminder->setDueByDate(true);
        $reminder->setDueByOdometer(false);
        $reminder->setCreatedAt(new DateTimeImmutable('2026-03-02 10:06:00'));
        $this->em->persist($reminder);

        $this->em->flush();

        $stationId = $station->getId()->toRfc4122();
        $eventId = $event->getId()->toRfc4122();
        $vehicleId = $vehicle->getId()->toRfc4122();
        $reminderId = $reminder->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);

        $stationEditPage = $this->request('GET', '/ui/admin/stations/'.$stationId.'/edit', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $stationEditPage->getStatusCode());
        $stationCsrf = $this->extractFormCsrf((string) $stationEditPage->getContent());

        $stationEditResponse = $this->request(
            'POST',
            '/ui/admin/stations/'.$stationId.'/edit',
            [
                'name' => 'Updated Station',
                'streetName' => '2 New Street',
                'postalCode' => '75002',
                'city' => 'Paris',
                '_token' => $stationCsrf,
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $stationEditResponse->getStatusCode());

        $stationList = $this->request('GET', '/ui/admin/stations', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $stationList->getStatusCode());
        self::assertStringContainsString('Updated Station', (string) $stationList->getContent());
        $stationDeleteCsrf = $this->extractDeleteCsrfForStation((string) $stationList->getContent(), $stationId);

        $stationDeleteResponse = $this->request(
            'POST',
            '/ui/admin/stations/'.$stationId.'/delete',
            ['_token' => $stationDeleteCsrf],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $stationDeleteResponse->getStatusCode());

        $afterStationDelete = $this->request('GET', '/ui/admin/stations', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $afterStationDelete->getStatusCode());
        self::assertStringNotContainsString('Updated Station', (string) $afterStationDelete->getContent());

        $eventShow = $this->request('GET', '/ui/admin/maintenance/events/'.$eventId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $eventShow->getStatusCode());
        self::assertStringContainsString('Initial maintenance event', (string) $eventShow->getContent());

        $eventEditPage = $this->request('GET', '/ui/admin/maintenance/events/'.$eventId.'/edit', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $eventEditPage->getStatusCode());
        $eventEditCsrf = $this->extractFormCsrf((string) $eventEditPage->getContent());

        $eventEditResponse = $this->request(
            'POST',
            '/ui/admin/maintenance/events/'.$eventId.'/edit',
            [
                'vehicleId' => $vehicleId,
                'eventType' => MaintenanceEventType::SERVICE->value,
                'occurredAt' => '2026-03-03T10:30',
                'description' => 'Updated maintenance event',
                'odometerKilometers' => '101000',
                'totalCostCents' => '25000',
                'currencyCode' => 'EUR',
                '_token' => $eventEditCsrf,
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $eventEditResponse->getStatusCode());

        $eventList = $this->request('GET', '/ui/admin/maintenance/events', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $eventList->getStatusCode());
        self::assertStringContainsString('Updated maintenance event', (string) $eventList->getContent());
        $eventDeleteCsrf = $this->extractDeleteCsrfForMaintenanceEvent((string) $eventList->getContent(), $eventId);

        $eventDeleteResponse = $this->request(
            'POST',
            '/ui/admin/maintenance/events/'.$eventId.'/delete',
            ['_token' => $eventDeleteCsrf],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $eventDeleteResponse->getStatusCode());

        $eventListAfterDelete = $this->request('GET', '/ui/admin/maintenance/events', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $eventListAfterDelete->getStatusCode());
        self::assertStringNotContainsString('Updated maintenance event', (string) $eventListAfterDelete->getContent());

        $reminderShow = $this->request('GET', '/ui/admin/maintenance/reminders/'.$reminderId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $reminderShow->getStatusCode());
        self::assertStringContainsString('Reminder detail rule', (string) $reminderShow->getContent());
    }

    /**
     * @param array<string, string|int|float|bool|null> $parameters
     * @param array<string, string>                     $server
     * @param array<string, string>                     $cookies
     */
    private function request(string $method, string $uri, array $parameters = [], array $server = [], array $cookies = []): Response
    {
        $request = Request::create($uri, $method, $parameters, $cookies, server: $server);
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
        self::assertMatchesRegularExpression('/name="_csrf_token" value="([^"]+)"/', $content);
        preg_match('/name="_csrf_token" value="([^"]+)"/', $content, $matches);
        $csrfToken = $matches[1] ?? null;
        self::assertIsString($csrfToken);
        self::assertNotSame('', $csrfToken);

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

    private function extractFormCsrf(string $content): string
    {
        self::assertMatchesRegularExpression('/name="_token" value="([^"]+)"/', $content);
        preg_match('/name="_token" value="([^"]+)"/', $content, $matches);
        $csrfToken = $matches[1] ?? null;
        self::assertIsString($csrfToken);
        self::assertNotSame('', $csrfToken);

        return $csrfToken;
    }

    private function extractDeleteCsrfForVehicle(string $content, string $vehicleId): string
    {
        $pattern = '#/ui/admin/vehicles/'.preg_quote($vehicleId, '#').'/delete.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
    }

    private function extractDeleteCsrfForImport(string $content, string $importId): string
    {
        $pattern = '#/ui/admin/imports/'.preg_quote($importId, '#').'/delete.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
    }

    private function extractDeleteCsrfForStation(string $content, string $stationId): string
    {
        $pattern = '#/ui/admin/stations/'.preg_quote($stationId, '#').'/delete.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
    }

    private function extractDeleteCsrfForMaintenanceEvent(string $content, string $eventId): string
    {
        $pattern = '#/ui/admin/maintenance/events/'.preg_quote($eventId, '#').'/delete.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
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
