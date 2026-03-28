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
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptLineEntity;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserIdentityEntity;
use App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class AdminBackofficeUiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();
        $this->client->disableReboot();
        $container = static::getContainer();

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
        $usersResponse = $this->request('GET', '/ui/admin/users', [], [], $sessionCookie);
        $identitiesResponse = $this->request('GET', '/ui/admin/identities', [], [], $sessionCookie);
        $securityActivitiesResponse = $this->request('GET', '/ui/admin/security-activities', [], [], $sessionCookie);
        $stationsResponse = $this->request('GET', '/ui/admin/stations', [], [], $sessionCookie);
        $vehiclesResponse = $this->request('GET', '/ui/admin/vehicles', [], [], $sessionCookie);
        $maintenanceEventsResponse = $this->request('GET', '/ui/admin/maintenance/events', [], [], $sessionCookie);
        $maintenanceRemindersResponse = $this->request('GET', '/ui/admin/maintenance/reminders', [], [], $sessionCookie);
        $receiptsResponse = $this->request('GET', '/ui/admin/receipts', [], [], $sessionCookie);
        $importsResponse = $this->request('GET', '/ui/admin/imports', [], [], $sessionCookie);
        $auditResponse = $this->request('GET', '/ui/admin/audit-logs', [], [], $sessionCookie);

        self::assertSame(Response::HTTP_FORBIDDEN, $dashboardResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $usersResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $identitiesResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $securityActivitiesResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $stationsResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $vehiclesResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $maintenanceEventsResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $maintenanceRemindersResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $receiptsResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $importsResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $auditResponse->getStatusCode());
    }

    public function testAdminCanAccessBackofficePagesAndSeeSeededData(): void
    {
        $adminEmail = 'ui.admin.allowed@example.com';
        $adminPassword = 'test1234';
        $admin = $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);

        $owner = $this->createUser('ui.owner@example.com', 'test1234', ['ROLE_USER']);
        $identity = new UserIdentityEntity();
        $identity->setId(Uuid::v7());
        $identity->setUser($owner);
        $identity->setProvider('google');
        $identity->setSubject('ui-owner-google-sub');
        $identity->setEmail('ui.owner@example.com');
        $this->em->persist($identity);

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

        $failedJob = new ImportJobEntity();
        $failedJob->setId(Uuid::v7());
        $failedJob->setOwner($owner);
        $failedJob->setStatus(ImportJobStatus::FAILED);
        $failedJob->setStorage('local');
        $failedJob->setFilePath('2026/02/22/ui-import-failed.pdf');
        $failedJob->setOriginalFilename('ui-import-failed.pdf');
        $failedJob->setMimeType('application/pdf');
        $failedJob->setFileSizeBytes(2048);
        $failedJob->setFileChecksumSha256(str_repeat('e', 64));
        $failedJob->setErrorPayload(json_encode(['reason' => 'ocr_failed'], JSON_THROW_ON_ERROR));
        $failedJob->setCreatedAt(new DateTimeImmutable('2026-02-22 09:30:00'));
        $failedJob->setUpdatedAt(new DateTimeImmutable('2026-02-22 09:35:00'));
        $failedJob->setFailedAt(new DateTimeImmutable('2026-02-22 09:35:00'));
        $failedJob->setRetentionUntil(new DateTimeImmutable('2026-03-22 09:35:00'));
        $this->em->persist($failedJob);

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

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setVehicle($vehicle);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-02-22 08:10:00'));
        $receipt->setTotalCents(18000);
        $receipt->setVatAmountCents(3000);
        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(10000);
        $line->setUnitPriceDeciCentsPerLiter(1800);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);
        $this->em->persist($receipt);

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
        self::assertStringContainsString('Needs attention now', (string) $dashboardResponse->getContent());
        self::assertStringContainsString('Inspect failures', (string) $dashboardResponse->getContent());
        self::assertStringContainsString('Review imports', (string) $dashboardResponse->getContent());
        self::assertStringContainsString('Open reminders', (string) $dashboardResponse->getContent());
        self::assertStringContainsString('Recent receipts', (string) $dashboardResponse->getContent());
        self::assertStringContainsString('Import queue snapshot', (string) $dashboardResponse->getContent());
        self::assertStringContainsString('ui-import.pdf', (string) $dashboardResponse->getContent());
        self::assertStringContainsString('ui-import-failed.pdf', (string) $dashboardResponse->getContent());
        self::assertStringContainsString((string) $receipt->getId(), (string) $dashboardResponse->getContent());

        $stationsResponse = $this->request('GET', '/ui/admin/stations', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $stationsResponse->getStatusCode());
        self::assertStringContainsString('UI Station', (string) $stationsResponse->getContent());

        $usersResponse = $this->request('GET', '/ui/admin/users', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $usersResponse->getStatusCode());
        self::assertStringContainsString('Users', (string) $usersResponse->getContent());
        self::assertStringContainsString('ui.owner@example.com', (string) $usersResponse->getContent());

        $identitiesResponse = $this->request('GET', '/ui/admin/identities', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $identitiesResponse->getStatusCode());
        self::assertStringContainsString('Identities', (string) $identitiesResponse->getContent());
        self::assertStringContainsString('ui-owner-google-sub', (string) $identitiesResponse->getContent());

        $vehiclesResponse = $this->request('GET', '/ui/admin/vehicles', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $vehiclesResponse->getStatusCode());
        self::assertStringContainsString('UI Vehicle', (string) $vehiclesResponse->getContent());
        self::assertStringContainsString('AA-123-UI', (string) $vehiclesResponse->getContent());

        $maintenanceEventsResponse = $this->request('GET', '/ui/admin/maintenance/events', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $maintenanceEventsResponse->getStatusCode());
        $maintenanceEventsContent = (string) $maintenanceEventsResponse->getContent();
        self::assertStringContainsString('Maintenance Events', $maintenanceEventsContent);
        self::assertStringContainsString('UI maintenance event', $maintenanceEventsContent);
        self::assertStringContainsString('UI Vehicle', $maintenanceEventsContent);
        self::assertStringContainsString('Vehicle', $maintenanceEventsContent);

        $maintenanceRemindersResponse = $this->request('GET', '/ui/admin/maintenance/reminders', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $maintenanceRemindersResponse->getStatusCode());
        $maintenanceRemindersContent = (string) $maintenanceRemindersResponse->getContent();
        self::assertStringContainsString('Maintenance Reminders', $maintenanceRemindersContent);
        self::assertStringContainsString('UI rule', $maintenanceRemindersContent);
        self::assertStringContainsString('UI Vehicle', $maintenanceRemindersContent);
        self::assertStringContainsString('Due by date', $maintenanceRemindersContent);

        $receiptsResponse = $this->request('GET', '/ui/admin/receipts', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $receiptsResponse->getStatusCode());
        self::assertStringContainsString((string) $receipt->getId(), (string) $receiptsResponse->getContent());

        $importsResponse = $this->request('GET', '/ui/admin/imports', ['status' => 'needs_review'], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $importsResponse->getStatusCode());
        $importsContent = (string) $importsResponse->getContent();
        self::assertStringContainsString('data-admin-sidebar-toggle', $importsContent);
        self::assertStringContainsString('aria-label="Hide admin menu"', $importsContent);
        self::assertStringContainsString('ui-import.pdf', $importsContent);
        self::assertStringContainsString('needs_review', $importsContent);
        self::assertStringContainsString('data-controller="row-link"', $importsContent);
        self::assertStringContainsString('Follow up now', $importsContent);
        self::assertStringContainsString('Review next pending', $importsContent);
        self::assertStringContainsString('Manual review needed', $importsContent);
        self::assertStringContainsString('Active filters', $importsContent);
        self::assertStringContainsString('Status: needs_review', $importsContent);

        $securityResponse = $this->request('GET', '/ui/admin/security-activities', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $securityResponse->getStatusCode());
        self::assertStringContainsString('Security Activities', (string) $securityResponse->getContent());
        self::assertStringContainsString('security.login.success', (string) $securityResponse->getContent());

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

    public function testAdminMaintenanceEventDetailKeepsFilteredReturnContext(): void
    {
        $adminEmail = 'ui.admin.maintenance.context@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.admin.maintenance.owner@example.com', 'test1234', ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setOwner($owner);
        $vehicle->setName('Context Vehicle');
        $vehicle->setPlateNumber('CTX-100-AA');
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-10 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-10 10:00:00'));
        $this->em->persist($vehicle);

        $event = new MaintenanceEventEntity();
        $event->setId(Uuid::v7());
        $event->setOwner($owner);
        $event->setVehicle($vehicle);
        $event->setEventType(MaintenanceEventType::SERVICE);
        $event->setOccurredAt(new DateTimeImmutable('2026-03-11 09:00:00'));
        $event->setDescription('Context maintenance event');
        $event->setOdometerKilometers(98000);
        $event->setTotalCostCents(15500);
        $event->setCurrencyCode('EUR');
        $event->setCreatedAt(new DateTimeImmutable('2026-03-11 09:00:00'));
        $event->setUpdatedAt(new DateTimeImmutable('2026-03-11 09:00:00'));
        $this->em->persist($event);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);
        $returnTo = '/ui/admin/maintenance/events?owner_id='.$owner->getId()->toRfc4122().'&event_type=service';

        $detailResponse = $this->request(
            'GET',
            '/ui/admin/maintenance/events/'.$event->getId()->toRfc4122().'?return_to='.rawurlencode($returnTo),
            [],
            [],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $content = (string) $detailResponse->getContent();
        $escapedReturnTo = htmlspecialchars($returnTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        self::assertStringContainsString('href="'.$escapedReturnTo.'"', $content);
        self::assertStringContainsString('name="_redirect" value="'.$escapedReturnTo.'"', $content);
    }

    public function testAdminMaintenanceReminderDetailShowsVehicleShortcutAndRelatedEventLink(): void
    {
        $adminEmail = 'ui.admin.reminder.shortcut@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.admin.reminder.shortcut.owner@example.com', 'test1234', ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setOwner($owner);
        $vehicle->setName('Reminder Shortcut Vehicle');
        $vehicle->setPlateNumber('RMD-123-AA');
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-11 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-11 10:00:00'));
        $this->em->persist($vehicle);

        $rule = new MaintenanceReminderRuleEntity();
        $rule->setId(Uuid::v7());
        $rule->setOwner($owner);
        $rule->setVehicle($vehicle);
        $rule->setName('Reminder Shortcut Rule');
        $rule->setTriggerMode(ReminderRuleTriggerMode::DATE);
        $rule->setEventType(MaintenanceEventType::SERVICE);
        $rule->setIntervalDays(180);
        $rule->setIntervalKilometers(null);
        $rule->setCreatedAt(new DateTimeImmutable('2026-03-11 10:05:00'));
        $rule->setUpdatedAt(new DateTimeImmutable('2026-03-11 10:05:00'));
        $this->em->persist($rule);

        $reminder = new MaintenanceReminderEntity();
        $reminder->setId(Uuid::v7());
        $reminder->setOwner($owner);
        $reminder->setVehicle($vehicle);
        $reminder->setRule($rule);
        $reminder->setDedupKey(hash('sha256', 'admin-reminder-shortcut'));
        $reminder->setDueAtDate(new DateTimeImmutable('2026-03-30 00:00:00'));
        $reminder->setDueAtOdometerKilometers(null);
        $reminder->setDueByDate(true);
        $reminder->setDueByOdometer(false);
        $reminder->setCreatedAt(new DateTimeImmutable('2026-03-11 10:06:00'));
        $this->em->persist($reminder);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);
        $returnTo = '/ui/admin/maintenance/reminders?vehicle_id='.$vehicle->getId()->toRfc4122();

        $detailResponse = $this->request(
            'GET',
            '/ui/admin/maintenance/reminders/'.$reminder->getId()->toRfc4122().'?return_to='.rawurlencode($returnTo),
            [],
            [],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $content = (string) $detailResponse->getContent();
        self::assertStringContainsString('Reminder Shortcut Vehicle', $content);
        self::assertStringContainsString('/ui/admin/vehicles/'.$vehicle->getId()->toRfc4122(), $content);
        self::assertStringContainsString('/ui/admin/receipts?vehicle_id='.$vehicle->getId()->toRfc4122(), $content);
        self::assertStringContainsString('/ui/admin/maintenance/reminders?vehicle_id='.$vehicle->getId()->toRfc4122(), $content);
        self::assertStringContainsString('/ui/admin/maintenance/events?vehicle_id='.$vehicle->getId()->toRfc4122(), $content);

        $filteredListResponse = $this->request('GET', $returnTo, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $filteredListResponse->getStatusCode());
        $filteredListContent = (string) $filteredListResponse->getContent();
        self::assertStringContainsString('Active filters', $filteredListContent);
        self::assertStringContainsString('Vehicle: Reminder Shortcut Vehicle', $filteredListContent);
    }

    public function testAdminCanToggleUserFlagsResetPasswordAndResendVerificationFromBackofficeUi(): void
    {
        $adminEmail = 'ui.admin.user.write@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $managed = $this->createUser('ui.managed.user@example.com', 'test1234', ['ROLE_USER']);
        $this->em->flush();

        $managedId = $managed->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);

        $listResponse = $this->request('GET', '/ui/admin/users?q=ui.managed.user@example.com', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $listResponse->getStatusCode());
        self::assertStringContainsString('ui.managed.user@example.com', (string) $listResponse->getContent());

        $activeToken = $this->extractToggleActiveCsrf((string) $listResponse->getContent(), $managedId);
        $deactivateResponse = $this->request(
            'POST',
            '/ui/admin/users/'.$managedId.'/toggle-active',
            ['_token' => $activeToken],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $deactivateResponse->getStatusCode());

        $afterDeactivate = $this->request('GET', '/ui/admin/users?is_active=0', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $afterDeactivate->getStatusCode());
        self::assertStringContainsString('inactive', (string) $afterDeactivate->getContent());

        $reactivateToken = $this->extractToggleActiveCsrf((string) $afterDeactivate->getContent(), $managedId);
        $reactivateResponse = $this->request(
            'POST',
            '/ui/admin/users/'.$managedId.'/toggle-active',
            ['_token' => $reactivateToken],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $reactivateResponse->getStatusCode());

        $adminList = $this->request('GET', '/ui/admin/users?q=ui.managed.user@example.com', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $adminList->getStatusCode());
        $adminToken = $this->extractToggleAdminCsrf((string) $adminList->getContent(), $managedId);
        $promoteResponse = $this->request(
            'POST',
            '/ui/admin/users/'.$managedId.'/toggle-admin',
            ['_token' => $adminToken],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $promoteResponse->getStatusCode());

        $onlyAdmins = $this->request('GET', '/ui/admin/users?role=admin', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $onlyAdmins->getStatusCode());
        self::assertStringContainsString('ui.managed.user@example.com', (string) $onlyAdmins->getContent());

        $toggleVerificationToken = $this->extractToggleVerificationCsrf((string) $adminList->getContent(), $managedId);
        $verifyResponse = $this->request(
            'POST',
            '/ui/admin/users/'.$managedId.'/toggle-verification',
            ['_token' => $toggleVerificationToken],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $verifyResponse->getStatusCode());

        $afterVerify = $this->request('GET', '/ui/admin/users?q=ui.managed.user@example.com', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $afterVerify->getStatusCode());
        self::assertStringContainsString('verified', (string) $afterVerify->getContent());

        $resendToken = $this->extractResendVerificationCsrf((string) $afterVerify->getContent(), $managedId);
        $resendResponse = $this->request(
            'POST',
            '/ui/admin/users/'.$managedId.'/resend-verification',
            ['_token' => $resendToken],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $resendResponse->getStatusCode());

        $afterRejectedResend = $this->request('GET', '/ui/admin/users?q=ui.managed.user@example.com', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $afterRejectedResend->getStatusCode());
        self::assertStringContainsString('User email is already verified.', (string) $afterRejectedResend->getContent());

        $resetToken = $this->extractResetPasswordCsrf((string) $afterVerify->getContent(), $managedId);
        $resetResponse = $this->request(
            'POST',
            '/ui/admin/users/'.$managedId.'/reset-password',
            ['_token' => $resetToken],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $resetResponse->getStatusCode());

        $afterReset = $this->request('GET', '/ui/admin/users?q=ui.managed.user@example.com', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $afterReset->getStatusCode());
        self::assertStringContainsString('Temporary password for ui.managed.user@example.com:', (string) $afterReset->getContent());
    }

    public function testAdminCanRelinkAndDeleteIdentityFromBackofficeUi(): void
    {
        $adminEmail = 'ui.admin.identity.write@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $ownerA = $this->createUser('ui.identity.owner.a@example.com', 'test1234', ['ROLE_USER']);
        $ownerB = $this->createUser('ui.identity.owner.b@example.com', 'test1234', ['ROLE_USER']);
        $identity = new UserIdentityEntity();
        $identity->setId(Uuid::v7());
        $identity->setUser($ownerA);
        $identity->setProvider('google');
        $identity->setSubject('ui-owner-subject-001');
        $identity->setEmail('ui.identity.owner.a@example.com');
        $this->em->persist($identity);
        $this->em->flush();

        $identityId = $identity->getId()->toRfc4122();
        $ownerBId = $ownerB->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);

        $listResponse = $this->request('GET', '/ui/admin/identities?q=ui-owner-subject-001', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $listResponse->getStatusCode());
        self::assertStringContainsString('ui-owner-subject-001', (string) $listResponse->getContent());

        $relinkToken = $this->extractIdentityRelinkCsrf((string) $listResponse->getContent(), $identityId);
        $relinkResponse = $this->request(
            'POST',
            '/ui/admin/identities/'.$identityId.'/relink',
            [
                '_token' => $relinkToken,
                'user_id' => $ownerBId,
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $relinkResponse->getStatusCode());

        $afterRelink = $this->request('GET', '/ui/admin/identities?user_id='.$ownerBId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $afterRelink->getStatusCode());
        self::assertStringContainsString('ui.identity.owner.b@example.com', (string) $afterRelink->getContent());

        $deleteToken = $this->extractIdentityDeleteCsrf((string) $afterRelink->getContent(), $identityId);
        $deleteResponse = $this->request(
            'POST',
            '/ui/admin/identities/'.$identityId.'/delete',
            ['_token' => $deleteToken],
            [],
            $sessionCookie,
        );
        self::assertContains($deleteResponse->getStatusCode(), [Response::HTTP_FOUND, Response::HTTP_SEE_OTHER]);

        $afterDelete = $this->request('GET', '/ui/admin/identities', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $afterDelete->getStatusCode());
        self::assertStringNotContainsString('ui-owner-subject-001', (string) $afterDelete->getContent());
        self::assertStringContainsString('No identities found.', (string) $afterDelete->getContent());
    }

    public function testAdminCanFilterSecurityActivitiesFromBackofficeUi(): void
    {
        $adminEmail = 'ui.admin.security.filter@example.com';
        $adminPassword = 'test1234';
        $admin = $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);

        $entry = new AdminAuditLogEntity();
        $entry->setId(Uuid::v7());
        $entry->setActorId($admin->getId());
        $entry->setActorEmail($adminEmail);
        $entry->setAction('security.login.failure');
        $entry->setTargetType('credential');
        $entry->setTargetId('ui.admin.security.filter@example.com');
        $entry->setDiffSummary([]);
        $entry->setMetadata(['reason' => 'Invalid credentials.']);
        $entry->setCorrelationId('corr-ui-security-001');
        $entry->setCreatedAt(new DateTimeImmutable('2026-03-01 16:45:00'));
        $this->em->persist($entry);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);
        $response = $this->request(
            'GET',
            '/ui/admin/security-activities?action=security.login.failure&actorId='.$admin->getId()->toRfc4122(),
            [],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('security.login.failure', (string) $response->getContent());
        self::assertStringContainsString('corr-ui-security-001', (string) $response->getContent());
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

    public function testAdminCanReparseNeedsReviewImportFromBackofficeUi(): void
    {
        $adminEmail = 'ui.admin.import.reparse@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.import.reparse.owner@example.com', 'test1234', ['ROLE_USER']);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($owner);
        $job->setStatus(ImportJobStatus::NEEDS_REVIEW);
        $job->setStorage('local');
        $job->setFilePath('2026/03/24/admin-reparse.jpg');
        $job->setOriginalFilename('admin-reparse.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('a', 64));
        $job->setErrorPayload(json_encode([
            'jobId' => 'admin-job-reparse',
            'provider' => 'ocr_space',
            'text' => "PETRO EST\nLECLERC BELLE IDEE 10100 ROMILLY SUR SEINE\nle 14/12/24 a 15:07:08\nMONTANT REEL 40,32 EUR\nCarburant = GAZOLE\n= 25,25 L\nPrix unit. = 1,597 EUR\nTVA 20,00% = 6,72 EUR",
            'pages' => [],
            'parsedDraft' => [
                'stationStreetName' => null,
                'creationPayload' => null,
            ],
            'status' => 'needs_review',
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-24 10:00:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-24 10:00:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-24 10:00:00'));
        $this->em->persist($job);
        $this->em->flush();

        $jobId = $job->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);

        $detailResponse = $this->request('GET', '/ui/admin/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('/ui/admin/imports/'.$jobId.'/reparse', $detailContent);
        $reparseToken = $this->extractReparseCsrfForImport((string) $detailResponse->getContent(), $jobId);

        $reparseResponse = $this->request(
            'POST',
            '/ui/admin/imports/'.$jobId.'/reparse',
            ['_token' => $reparseToken],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_FOUND, $reparseResponse->getStatusCode());

        $this->em->clear();
        $updated = $this->em->find(ImportJobEntity::class, $jobId);
        self::assertInstanceOf(ImportJobEntity::class, $updated);
        $payload = json_decode((string) $updated->getErrorPayload(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertArrayHasKey('parsedDraft', $payload);
        self::assertIsArray($payload['parsedDraft']);
        self::assertSame('LECLERC BELLE IDEE', $payload['parsedDraft']['stationStreetName'] ?? null);
        self::assertArrayHasKey('creationPayload', $payload['parsedDraft']);
        self::assertIsArray($payload['parsedDraft']['creationPayload']);
        self::assertSame('LECLERC BELLE IDEE', $payload['parsedDraft']['creationPayload']['stationStreetName'] ?? null);

        $audit = $this->em->getRepository(AdminAuditLogEntity::class)->findOneBy(['action' => 'admin.import.reparse.ui']);
        self::assertInstanceOf(AdminAuditLogEntity::class, $audit);
        self::assertSame($jobId, $audit->getTargetId());
    }

    public function testAdminReviewHighlightsMissingIssuedAtWhenOcrDidNotDetectDate(): void
    {
        $adminEmail = 'ui.admin.import.missing-date@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.import.missing-date.owner@example.com', 'test1234', ['ROLE_USER']);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($owner);
        $job->setStatus(ImportJobStatus::NEEDS_REVIEW);
        $job->setStorage('local');
        $job->setFilePath('2026/03/24/admin-missing-date.jpg');
        $job->setOriginalFilename('admin-missing-date.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('c', 64));
        $job->setErrorPayload(json_encode([
            'parsedDraft' => [
                'stationName' => 'TOTAL',
                'stationStreetName' => '40 Rue Robert Schuman',
                'stationPostalCode' => 'L-5751',
                'stationCity' => 'FRISANGE',
                'issuedAt' => null,
                'lines' => [[
                    'fuelType' => 'sp98',
                    'quantityMilliLiters' => 51240,
                    'unitPriceDeciCentsPerLiter' => 1068,
                    'vatRatePercent' => 5,
                ]],
                'issues' => ['issued_at_missing'],
                'creationPayload' => null,
            ],
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-24 11:20:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-24 11:20:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-24 11:20:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);
        $jobId = $job->getId()->toRfc4122();

        $reviewResponse = $this->request('GET', '/ui/admin/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $reviewResponse->getStatusCode());
        $reviewContent = (string) $reviewResponse->getContent();
        self::assertStringContainsString('Date required before finalization', $reviewContent);
        self::assertStringContainsString('Required for this import: OCR did not detect the receipt date.', $reviewContent);
        self::assertStringContainsString('name="issuedAt"', $reviewContent);
    }

    public function testAdminCanFinalizeNeedsReviewImportWithMultipleLinesFromBackofficeUi(): void
    {
        $adminEmail = 'ui.admin.import.multiline@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.import.multiline.owner@example.com', 'test1234', ['ROLE_USER']);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($owner);
        $job->setStatus(ImportJobStatus::NEEDS_REVIEW);
        $job->setStorage('local');
        $job->setFilePath('2026/03/25/admin-multiline.jpg');
        $job->setOriginalFilename('admin-multiline.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('d', 64));
        $job->setErrorPayload(json_encode([
            'parsedDraft' => [
                'issuedAt' => '2026-03-25T12:20:00+00:00',
                'stationName' => 'TOTAL ENERGIES',
                'stationStreetName' => '1 Rue de Rivoli',
                'stationPostalCode' => '75001',
                'stationCity' => 'Paris',
                'lines' => [
                    [
                        'fuelType' => 'diesel',
                        'quantityMilliLiters' => 28000,
                        'unitPriceDeciCentsPerLiter' => 1810,
                        'vatRatePercent' => 20,
                    ],
                    [
                        'fuelType' => 'sp95',
                        'quantityMilliLiters' => 12000,
                        'unitPriceDeciCentsPerLiter' => 1760,
                        'vatRatePercent' => 20,
                    ],
                ],
                'creationPayload' => null,
            ],
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-25 12:21:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-25 12:21:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-25 12:21:00'));
        $this->em->persist($job);
        $this->em->flush();

        $jobId = $job->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);

        $reviewResponse = $this->request('GET', '/ui/admin/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $reviewResponse->getStatusCode());
        $reviewContent = (string) $reviewResponse->getContent();
        self::assertStringContainsString('Receipt lines', $reviewContent);
        self::assertStringContainsString('name="lines[0][fuelType]"', $reviewContent);
        self::assertStringContainsString('name="lines[1][fuelType]"', $reviewContent);
        $csrfToken = $this->extractFinalizeCsrfForImport((string) $reviewResponse->getContent(), $jobId);

        $finalizeResponse = $this->request(
            'POST',
            '/ui/admin/imports/'.$jobId.'/finalize',
            [
                '_token' => $csrfToken,
                'issuedAt' => '2026-03-25T12:20',
                'stationName' => 'TOTAL ENERGIES',
                'stationStreetName' => '1 Rue de Rivoli',
                'stationPostalCode' => '75001',
                'stationCity' => 'Paris',
                'lines' => [
                    [
                        'fuelType' => 'diesel',
                        'quantityMilliLiters' => '28000',
                        'unitPriceDeciCentsPerLiter' => '1810',
                        'vatRatePercent' => '20',
                    ],
                    [
                        'fuelType' => 'sp95',
                        'quantityMilliLiters' => '12000',
                        'unitPriceDeciCentsPerLiter' => '1760',
                        'vatRatePercent' => '20',
                    ],
                ],
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_FOUND, $finalizeResponse->getStatusCode());

        $this->em->clear();
        $updated = $this->em->find(ImportJobEntity::class, $jobId);
        self::assertInstanceOf(ImportJobEntity::class, $updated);
        self::assertSame(ImportJobStatus::PROCESSED, $updated->getStatus());

        $savedReceipt = $this->em->getRepository(ReceiptEntity::class)->findOneBy([]);
        self::assertInstanceOf(ReceiptEntity::class, $savedReceipt);
        self::assertCount(2, $savedReceipt->getLines());
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

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setVehicle($vehicle);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-02 09:50:00'));
        $receipt->setTotalCents(19000);
        $receipt->setVatAmountCents(3167);
        $receiptLine = new ReceiptLineEntity();
        $receiptLine->setId(Uuid::v7());
        $receiptLine->setFuelType('diesel');
        $receiptLine->setQuantityMilliLiters(10000);
        $receiptLine->setUnitPriceDeciCentsPerLiter(1900);
        $receiptLine->setVatRatePercent(20);
        $receipt->addLine($receiptLine);
        $this->em->persist($receipt);

        $receiptImport = new ImportJobEntity();
        $receiptImport->setId(Uuid::v7());
        $receiptImport->setOwner($owner);
        $receiptImport->setStatus(ImportJobStatus::PROCESSED);
        $receiptImport->setStorage('local');
        $receiptImport->setFilePath('2026/03/02/admin-receipt-related.jpg');
        $receiptImport->setOriginalFilename('admin-receipt-related.jpg');
        $receiptImport->setMimeType('image/jpeg');
        $receiptImport->setFileSizeBytes(64000);
        $receiptImport->setFileChecksumSha256(str_repeat('q', 64));
        $receiptImport->setErrorPayload(json_encode([
            'status' => 'processed',
            'finalizedReceiptId' => $receipt->getId()->toRfc4122(),
        ], JSON_THROW_ON_ERROR));
        $receiptImport->setCreatedAt(new DateTimeImmutable('2026-03-02 09:55:00'));
        $receiptImport->setUpdatedAt(new DateTimeImmutable('2026-03-02 09:55:00'));
        $receiptImport->setCompletedAt(new DateTimeImmutable('2026-03-02 09:55:00'));
        $receiptImport->setRetentionUntil(new DateTimeImmutable('2026-04-02 09:55:00'));
        $this->em->persist($receiptImport);

        $this->em->flush();

        $stationId = $station->getId()->toRfc4122();
        $eventId = $event->getId()->toRfc4122();
        $vehicleId = $vehicle->getId()->toRfc4122();
        $reminderId = $reminder->getId()->toRfc4122();
        $receiptId = $receipt->getId()->toRfc4122();
        $receiptImportId = $receiptImport->getId()->toRfc4122();
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

        $eventReturnTo = '/ui/admin/maintenance/events?vehicle_id='.$vehicleId.'&event_type='.MaintenanceEventType::SERVICE->value;
        $eventShow = $this->request('GET', '/ui/admin/maintenance/events/'.$eventId.'?return_to='.rawurlencode($eventReturnTo), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $eventShow->getStatusCode());
        $eventShowContent = (string) $eventShow->getContent();
        self::assertStringContainsString('Initial maintenance event', $eventShowContent);
        self::assertStringContainsString('/ui/admin/vehicles/'.$vehicleId, $eventShowContent);
        self::assertStringContainsString('/ui/admin/receipts?vehicle_id='.$vehicleId, $eventShowContent);
        self::assertStringContainsString('/ui/admin/maintenance/reminders?vehicle_id='.$vehicleId, $eventShowContent);
        self::assertStringContainsString('/ui/admin/maintenance/events?vehicle_id='.$vehicleId, $eventShowContent);
        self::assertStringContainsString('return_to=', $eventShowContent);

        $eventEditPage = $this->request('GET', '/ui/admin/maintenance/events/'.$eventId.'/edit?return_to='.rawurlencode($eventReturnTo), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $eventEditPage->getStatusCode());
        $eventEditContent = (string) $eventEditPage->getContent();
        self::assertStringContainsString('name="_return_to" value="'.$eventReturnTo.'"', $eventEditContent);
        self::assertStringContainsString('href="'.$eventReturnTo.'"', $eventEditContent);
        $eventEditCsrf = $this->extractFormCsrf($eventEditContent);

        $eventEditResponse = $this->request(
            'POST',
            '/ui/admin/maintenance/events/'.$eventId.'/edit?return_to='.rawurlencode($eventReturnTo),
            [
                'vehicleId' => $vehicleId,
                'eventType' => MaintenanceEventType::SERVICE->value,
                'occurredAt' => '2026-03-03T10:30',
                'description' => 'Updated maintenance event',
                'odometerKilometers' => '101000',
                'totalCostCents' => '25000',
                'currencyCode' => 'EUR',
                '_token' => $eventEditCsrf,
                '_return_to' => $eventReturnTo,
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $eventEditResponse->getStatusCode());
        self::assertSame($eventReturnTo, $eventEditResponse->headers->get('Location'));

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

        $receiptList = $this->request('GET', '/ui/admin/receipts', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $receiptList->getStatusCode());
        $receiptListContent = (string) $receiptList->getContent();
        self::assertStringContainsString('System-wide receipt ledger', $receiptListContent);
        self::assertStringContainsString('Vehicle', $receiptListContent);
        self::assertStringContainsString('Station', $receiptListContent);
        self::assertStringContainsString('/ui/admin/receipts/'.$receiptId.'/edit', $receiptListContent);
        self::assertStringContainsString('return_to=', $receiptListContent);

        $receiptReturnTo = '/ui/admin/receipts?context=admin-support';
        $receiptShow = $this->request('GET', '/ui/admin/receipts/'.$receiptId.'?return_to='.rawurlencode($receiptReturnTo), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $receiptShow->getStatusCode());
        $receiptShowContent = (string) $receiptShow->getContent();
        self::assertStringContainsString('Receipt Detail', $receiptShowContent);
        self::assertStringContainsString('diesel', $receiptShowContent);
        self::assertStringContainsString('/ui/admin/vehicles/'.$vehicleId, $receiptShowContent);
        self::assertStringContainsString('/ui/admin/imports/'.$receiptImportId, $receiptShowContent);
        self::assertStringContainsString('Vehicle', $receiptShowContent);
        self::assertStringContainsString('Station', $receiptShowContent);
        self::assertStringContainsString('Related imports', $receiptShowContent);
        self::assertStringContainsString('Support continuity', $receiptShowContent);
        self::assertStringContainsString('Open vehicle', $receiptShowContent);
        self::assertStringContainsString('Open station', $receiptShowContent);
        self::assertStringContainsString('Open related import', $receiptShowContent);
        self::assertStringContainsString('return_to=', $receiptShowContent);

        $receiptEditPage = $this->request('GET', '/ui/admin/receipts/'.$receiptId.'/edit?return_to='.rawurlencode('/ui/admin/receipts?context=edit-flow'), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $receiptEditPage->getStatusCode());
        $receiptEditContent = (string) $receiptEditPage->getContent();
        self::assertStringContainsString('name="_return_to" value="/ui/admin/receipts?context=edit-flow"', $receiptEditContent);
        self::assertStringContainsString('href="/ui/admin/receipts?context=edit-flow"', $receiptEditContent);
        $receiptCsrf = $this->extractFormCsrf($receiptEditContent);

        $receiptEditResponse = $this->request(
            'POST',
            '/ui/admin/receipts/'.$receiptId.'/edit',
            [
                '_token' => $receiptCsrf,
                '_return_to' => '/ui/admin/receipts?context=edit-flow',
                'lines' => [[
                    'fuelType' => 'sp95',
                    'quantityMilliLiters' => '12000',
                    'unitPriceDeciCentsPerLiter' => '1700',
                    'vatRatePercent' => '20',
                ]],
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $receiptEditResponse->getStatusCode());
        self::assertSame('/ui/admin/receipts?context=edit-flow', $receiptEditResponse->headers->get('Location'));

        $afterReceiptEdit = $this->request('GET', '/ui/admin/receipts/'.$receiptId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $afterReceiptEdit->getStatusCode());
        self::assertStringContainsString('sp95', (string) $afterReceiptEdit->getContent());
        $receiptDeleteToken = $this->extractDeleteCsrfForReceipt((string) $receiptList->getContent(), $receiptId);

        $receiptDeleteResponse = $this->request(
            'POST',
            '/ui/admin/receipts/'.$receiptId.'/delete',
            ['_token' => $receiptDeleteToken],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_FOUND, $receiptDeleteResponse->getStatusCode());

        $afterReceiptDelete = $this->request('GET', '/ui/admin/receipts', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $afterReceiptDelete->getStatusCode());
        self::assertStringNotContainsString($receiptId, (string) $afterReceiptDelete->getContent());
    }

    public function testAdminProcessedImportDetailShowsReceiptContinuityShortcuts(): void
    {
        $adminEmail = 'ui.admin.import.processed.shortcut@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.admin.import.processed.owner@example.com', 'test1234', ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('Processed Import Vehicle');
        $vehicle->setPlateNumber('PI-300-AA');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-26 09:40:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-26 09:40:00'));
        $this->em->persist($vehicle);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Processed Import Station');
        $station->setStreetName('14 Queue Avenue');
        $station->setPostalCode('75011');
        $station->setCity('Paris');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setVehicle($vehicle);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-26 10:00:00'));
        $receipt->setTotalCents(4200);
        $receipt->setVatAmountCents(700);
        $this->em->persist($receipt);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setReceipt($receipt);
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(25000);
        $line->setUnitPriceDeciCentsPerLiter(1680);
        $line->setVatRatePercent(20);
        $this->em->persist($line);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($owner);
        $job->setStatus(ImportJobStatus::PROCESSED);
        $job->setStorage('local');
        $job->setFilePath('2026/03/26/admin-processed.jpg');
        $job->setOriginalFilename('admin-processed.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('r', 64));
        $job->setErrorPayload(json_encode([
            'status' => 'processed',
            'finalizedReceiptId' => $receipt->getId()->toRfc4122(),
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-26 10:01:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-26 10:01:00'));
        $job->setCompletedAt(new DateTimeImmutable('2026-03-26 10:01:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-26 10:01:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);
        $jobId = $job->getId()->toRfc4122();
        $receiptId = $receipt->getId()->toRfc4122();

        $detailResponse = $this->request('GET', '/ui/admin/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('Import completed', $detailContent);
        self::assertStringContainsString('/ui/admin/receipts/'.$receiptId, $detailContent);
        self::assertStringContainsString('Open created receipt', $detailContent);
        self::assertStringContainsString('Receipt continuity', $detailContent);
        self::assertStringContainsString('/ui/admin/vehicles/'.$vehicle->getId()->toRfc4122(), $detailContent);
        self::assertStringContainsString('/ui/admin/stations/'.$station->getId()->toRfc4122(), $detailContent);
    }

    public function testAdminVehicleListAndDetailExposeReceiptAndMaintenanceShortcuts(): void
    {
        $adminEmail = 'ui.admin.vehicle.shortcuts@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.admin.vehicle.owner@example.com', 'test1234', ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('Garage Admin Vehicle');
        $vehicle->setPlateNumber('GA-290-VE');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-27 09:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-27 09:05:00'));
        $this->em->persist($vehicle);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Garage Admin Station');
        $station->setStreetName('10 Support Street');
        $station->setPostalCode('75010');
        $station->setCity('Paris');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setVehicle($vehicle);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-27 08:45:00'));
        $receipt->setTotalCents(6200);
        $receipt->setVatAmountCents(1033);
        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(4000);
        $line->setUnitPriceDeciCentsPerLiter(1550);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);
        $this->em->persist($receipt);

        $event = new MaintenanceEventEntity();
        $event->setId(Uuid::v7());
        $event->setOwner($owner);
        $event->setVehicle($vehicle);
        $event->setEventType(MaintenanceEventType::SERVICE);
        $event->setOccurredAt(new DateTimeImmutable('2026-03-26 10:00:00'));
        $event->setDescription('Garage support event');
        $event->setOdometerKilometers(85000);
        $event->setTotalCostCents(18000);
        $event->setCurrencyCode('EUR');
        $event->setCreatedAt(new DateTimeImmutable('2026-03-26 10:00:00'));
        $event->setUpdatedAt(new DateTimeImmutable('2026-03-26 10:00:00'));
        $this->em->persist($event);

        $rule = new MaintenanceReminderRuleEntity();
        $rule->setId(Uuid::v7());
        $rule->setOwner($owner);
        $rule->setVehicle($vehicle);
        $rule->setName('Garage support rule');
        $rule->setTriggerMode(ReminderRuleTriggerMode::DATE);
        $rule->setEventType(MaintenanceEventType::SERVICE);
        $rule->setIntervalDays(365);
        $rule->setIntervalKilometers(null);
        $rule->setCreatedAt(new DateTimeImmutable('2026-03-26 10:05:00'));
        $rule->setUpdatedAt(new DateTimeImmutable('2026-03-26 10:05:00'));
        $this->em->persist($rule);

        $reminder = new MaintenanceReminderEntity();
        $reminder->setId(Uuid::v7());
        $reminder->setOwner($owner);
        $reminder->setVehicle($vehicle);
        $reminder->setRule($rule);
        $reminder->setDedupKey(hash('sha256', 'garage-admin-vehicle-reminder'));
        $reminder->setDueAtDate(new DateTimeImmutable('2026-03-28 00:00:00'));
        $reminder->setDueAtOdometerKilometers(null);
        $reminder->setDueByDate(true);
        $reminder->setDueByOdometer(false);
        $reminder->setCreatedAt(new DateTimeImmutable('2026-03-27 11:00:00'));
        $this->em->persist($reminder);

        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);
        $vehicleId = $vehicle->getId()->toRfc4122();

        $listResponse = $this->request('GET', '/ui/admin/vehicles', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $listResponse->getStatusCode());
        $listContent = (string) $listResponse->getContent();
        self::assertStringContainsString('Garage Admin Vehicle', $listContent);
        self::assertStringContainsString('1 receipt', $listContent);
        self::assertStringContainsString('1 event', $listContent);
        self::assertStringContainsString('1 due reminder', $listContent);
        self::assertStringContainsString('Receipts', $listContent);
        self::assertStringContainsString('Maintenance', $listContent);

        $detailResponse = $this->request('GET', '/ui/admin/vehicles/'.$vehicleId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('Support shortcuts', $detailContent);
        self::assertStringContainsString('Open receipts', $detailContent);
        self::assertStringContainsString('Open maintenance events', $detailContent);
        self::assertStringContainsString('Open reminders', $detailContent);
        self::assertStringContainsString('Recent receipts', $detailContent);
        self::assertStringContainsString('Garage Admin Station', $detailContent);

        $filteredReceiptsResponse = $this->request('GET', '/ui/admin/receipts?vehicle_id='.$vehicleId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $filteredReceiptsResponse->getStatusCode());
        self::assertStringContainsString('Context filter active', (string) $filteredReceiptsResponse->getContent());
        self::assertStringContainsString('Vehicle:', (string) $filteredReceiptsResponse->getContent());
    }

    public function testAdminDuplicateImportDetailShowsShortcutToOriginalImport(): void
    {
        $adminEmail = 'ui.admin.import.duplicate.shortcut@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.admin.import.duplicate.owner@example.com', 'test1234', ['ROLE_USER']);

        $originalJob = new ImportJobEntity();
        $originalJob->setId(Uuid::v7());
        $originalJob->setOwner($owner);
        $originalJob->setStatus(ImportJobStatus::PROCESSED);
        $originalJob->setStorage('local');
        $originalJob->setFilePath('2026/03/26/admin-original.jpg');
        $originalJob->setOriginalFilename('admin-original.jpg');
        $originalJob->setMimeType('image/jpeg');
        $originalJob->setFileSizeBytes(64000);
        $originalJob->setFileChecksumSha256(str_repeat('g', 64));
        $originalJob->setErrorPayload('{"status":"processed"}');
        $originalJob->setCreatedAt(new DateTimeImmutable('2026-03-26 10:30:00'));
        $originalJob->setUpdatedAt(new DateTimeImmutable('2026-03-26 10:30:00'));
        $originalJob->setRetentionUntil(new DateTimeImmutable('2026-04-26 10:30:00'));
        $this->em->persist($originalJob);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($owner);
        $job->setStatus(ImportJobStatus::DUPLICATE);
        $job->setStorage('local');
        $job->setFilePath('2026/03/26/admin-duplicate.jpg');
        $job->setOriginalFilename('admin-duplicate.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('h', 64));
        $job->setErrorPayload(json_encode([
            'status' => 'duplicate',
            'duplicateOfImportJobId' => $originalJob->getId()->toRfc4122(),
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-26 10:35:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-26 10:35:00'));
        $job->setCompletedAt(new DateTimeImmutable('2026-03-26 10:35:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-26 10:35:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);
        $jobId = $job->getId()->toRfc4122();
        $originalJobId = $originalJob->getId()->toRfc4122();

        $detailResponse = $this->request('GET', '/ui/admin/imports/'.$jobId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('Duplicate import', $detailContent);
        self::assertStringContainsString('/ui/admin/imports/'.$originalJobId, $detailContent);
        self::assertStringContainsString('Open original import', $detailContent);
    }

    public function testAdminStationListAndDetailExposeReceiptShortcuts(): void
    {
        $adminEmail = 'ui.admin.station.shortcuts@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.admin.station.owner@example.com', 'test1234', ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('Garage Station Vehicle');
        $vehicle->setPlateNumber('GA-290-ST');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-28 08:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-28 08:00:00'));
        $this->em->persist($vehicle);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Garage Support Station');
        $station->setStreetName('22 Queue Street');
        $station->setPostalCode('69002');
        $station->setCity('Lyon');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setVehicle($vehicle);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-28 07:45:00'));
        $receipt->setTotalCents(7100);
        $receipt->setVatAmountCents(1183);
        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('sp98');
        $line->setQuantityMilliLiters(5000);
        $line->setUnitPriceDeciCentsPerLiter(1420);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);
        $this->em->persist($receipt);
        $this->em->flush();

        $stationId = $station->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);

        $listResponse = $this->request('GET', '/ui/admin/stations', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $listResponse->getStatusCode());
        $listContent = (string) $listResponse->getContent();
        self::assertStringContainsString('Garage Support Station', $listContent);
        self::assertStringContainsString('1 receipt', $listContent);
        self::assertStringContainsString('Receipts', $listContent);

        $detailResponse = $this->request('GET', '/ui/admin/stations/'.$stationId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('Support shortcuts', $detailContent);
        self::assertStringContainsString('Open receipts', $detailContent);
        self::assertStringContainsString('Recent receipts', $detailContent);
        self::assertStringContainsString('Garage Station Vehicle', $detailContent);

        $filteredReceiptsResponse = $this->request('GET', '/ui/admin/receipts?station_id='.$stationId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $filteredReceiptsResponse->getStatusCode());
        self::assertStringContainsString('Context filter active', (string) $filteredReceiptsResponse->getContent());
        self::assertStringContainsString('Station:', (string) $filteredReceiptsResponse->getContent());
    }

    public function testAdminDuplicateImportDetailCanShortcutToExistingReceipt(): void
    {
        $adminEmail = 'ui.admin.import.duplicate.receipt@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.admin.import.duplicate.receipt.owner@example.com', 'test1234', ['ROLE_USER']);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('PETRO EST');
        $station->setStreetName('LECLERC SEZANNE HYPER');
        $station->setPostalCode('51120');
        $station->setCity('SEZANNE');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-26 10:45:00'));
        $receipt->setTotalCents(7147);
        $receipt->setVatAmountCents(1191);
        $this->em->persist($receipt);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setReceipt($receipt);
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(41000);
        $line->setUnitPriceDeciCentsPerLiter(1743);
        $line->setVatRatePercent(20);
        $this->em->persist($line);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($owner);
        $job->setStatus(ImportJobStatus::DUPLICATE);
        $job->setStorage('local');
        $job->setFilePath('2026/03/26/admin-duplicate-receipt.jpg');
        $job->setOriginalFilename('admin-duplicate-receipt.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('y', 64));
        $job->setErrorPayload(json_encode([
            'status' => 'duplicate',
            'duplicateOfReceiptId' => $receipt->getId()->toRfc4122(),
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-26 10:50:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-26 10:50:00'));
        $job->setCompletedAt(new DateTimeImmutable('2026-03-26 10:50:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-26 10:50:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);
        $detailResponse = $this->request('GET', '/ui/admin/imports/'.$job->getId()->toRfc4122(), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('Duplicate import', $detailContent);
        self::assertStringContainsString('/ui/admin/receipts/'.$receipt->getId()->toRfc4122(), $detailContent);
        self::assertStringContainsString('Open existing receipt', $detailContent);
    }

    public function testAdminDuplicateImportDetailDoesNotShowDeadLinksWhenTargetsAreGone(): void
    {
        $adminEmail = 'ui.admin.import.duplicate.missing@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.admin.import.duplicate.missing.owner@example.com', 'test1234', ['ROLE_USER']);

        $missingReceiptId = Uuid::v7()->toRfc4122();
        $missingImportId = Uuid::v7()->toRfc4122();

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($owner);
        $job->setStatus(ImportJobStatus::DUPLICATE);
        $job->setStorage('local');
        $job->setFilePath('2026/03/26/admin-duplicate-missing.jpg');
        $job->setOriginalFilename('admin-duplicate-missing.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('z', 64));
        $job->setErrorPayload(json_encode([
            'status' => 'duplicate',
            'duplicateOfReceiptId' => $missingReceiptId,
            'duplicateOfImportJobId' => $missingImportId,
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-26 10:50:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-26 10:50:00'));
        $job->setCompletedAt(new DateTimeImmutable('2026-03-26 10:50:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-26 10:50:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);
        $detailResponse = $this->request('GET', '/ui/admin/imports/'.$job->getId()->toRfc4122(), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('Duplicate import', $detailContent);
        self::assertStringContainsString('The linked receipt or original import is no longer available.', $detailContent);
        self::assertStringNotContainsString('Open existing receipt', $detailContent);
        self::assertStringNotContainsString('Open original import', $detailContent);
        self::assertStringNotContainsString('/ui/admin/receipts/'.$missingReceiptId, $detailContent);
        self::assertStringNotContainsString('/ui/admin/imports/'.$missingImportId, $detailContent);
    }

    public function testAdminNeedsReviewImportDetailShowsTriageSummaryForOcrFallback(): void
    {
        $adminEmail = 'ui.admin.import.fallback@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.admin.import.fallback.owner@example.com', 'test1234', ['ROLE_USER']);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($owner);
        $job->setStatus(ImportJobStatus::NEEDS_REVIEW);
        $job->setStorage('local');
        $job->setFilePath('2026/03/26/admin-fallback.jpg');
        $job->setOriginalFilename('admin-fallback.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('k', 64));
        $job->setOcrRetryCount(0);
        $job->setErrorPayload(json_encode([
            'status' => 'needs_review',
            'provider' => 'ocr_unavailable_fallback',
            'fingerprint' => 'checksum-sha256:v1:'.str_repeat('k', 64),
            'fallbackReason' => 'ocr_provider_retryable_exhausted',
            'fallbackStrategy' => 'manual_review',
            'retryCount' => 3,
            'parsedDraft' => [
                'issues' => [
                    'OCR provider unavailable after retries: timeout',
                    'Manual review remains available.',
                ],
            ],
        ], JSON_THROW_ON_ERROR));
        $job->setCreatedAt(new DateTimeImmutable('2026-03-26 11:00:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-26 11:05:00'));
        $job->setStartedAt(new DateTimeImmutable('2026-03-26 11:01:00'));
        $job->setCompletedAt(new DateTimeImmutable('2026-03-26 11:05:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-26 11:00:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);
        $detailResponse = $this->request('GET', '/ui/admin/imports/'.$job->getId()->toRfc4122(), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('Triage summary', $detailContent);
        self::assertStringContainsString('Retry count at terminal state', $detailContent);
        self::assertStringContainsString('ocr_provider_retryable_exhausted', $detailContent);
        self::assertStringContainsString('Detected issues', $detailContent);
        self::assertStringContainsString('2', $detailContent);
    }

    public function testAdminFailedImportDetailShowsTriageSummaryFromRawFailurePayload(): void
    {
        $adminEmail = 'ui.admin.import.failed@example.com';
        $adminPassword = 'test1234';
        $this->createUser($adminEmail, $adminPassword, ['ROLE_ADMIN']);
        $owner = $this->createUser('ui.admin.import.failed.owner@example.com', 'test1234', ['ROLE_USER']);

        $job = new ImportJobEntity();
        $job->setId(Uuid::v7());
        $job->setOwner($owner);
        $job->setStatus(ImportJobStatus::FAILED);
        $job->setStorage('local');
        $job->setFilePath('2026/03/26/admin-failed.jpg');
        $job->setOriginalFilename('admin-failed.jpg');
        $job->setMimeType('image/jpeg');
        $job->setFileSizeBytes(64000);
        $job->setFileChecksumSha256(str_repeat('m', 64));
        $job->setOcrRetryCount(2);
        $job->setErrorPayload('ocr_provider_permanent: provider quota exceeded');
        $job->setCreatedAt(new DateTimeImmutable('2026-03-26 11:10:00'));
        $job->setUpdatedAt(new DateTimeImmutable('2026-03-26 11:12:00'));
        $job->setStartedAt(new DateTimeImmutable('2026-03-26 11:11:00'));
        $job->setFailedAt(new DateTimeImmutable('2026-03-26 11:12:00'));
        $job->setRetentionUntil(new DateTimeImmutable('2026-04-26 11:10:00'));
        $this->em->persist($job);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($adminEmail, $adminPassword);
        $detailResponse = $this->request('GET', '/ui/admin/imports/'.$job->getId()->toRfc4122(), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $detailResponse->getStatusCode());
        $detailContent = (string) $detailResponse->getContent();
        self::assertStringContainsString('Triage summary', $detailContent);
        self::assertStringContainsString('Terminal detail', $detailContent);
        self::assertStringContainsString('ocr_provider_permanent: provider quota exceeded', $detailContent);
        self::assertStringContainsString('OCR retry count', $detailContent);
        self::assertStringContainsString('2', $detailContent);
    }

    /**
     * @param array<string, string|int|float|bool|array<int, array<string, string>>|null> $parameters
     * @param array<string, string>                                                       $server
     * @param array<string, string>                                                       $cookies
     */
    private function request(string $method, string $uri, array $parameters = [], array $server = [], array $cookies = []): Response
    {
        $this->client->request($method, $uri, $parameters, [], $server);

        return $this->client->getResponse();
    }

    /** @return array<string, string> */
    private function loginWithUiForm(string $email, string $password): array
    {
        $loginPageResponse = $this->request('GET', '/ui/login');
        self::assertSame(Response::HTTP_OK, $loginPageResponse->getStatusCode());

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
        );

        self::assertSame(Response::HTTP_FOUND, $loginResponse->getStatusCode());

        return [];
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

    private function extractReparseCsrfForImport(string $content, string $importId): string
    {
        $pattern = '#/ui/admin/imports/'.preg_quote($importId, '#').'/reparse.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
    }

    private function extractFinalizeCsrfForImport(string $content, string $importId): string
    {
        $pattern = '#/ui/admin/imports/'.preg_quote($importId, '#').'/finalize.*?name="_token" value="([^"]+)"#s';
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

    private function extractDeleteCsrfForReceipt(string $content, string $receiptId): string
    {
        $pattern = '#/ui/admin/receipts/'.preg_quote($receiptId, '#').'/delete.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
    }

    private function extractToggleActiveCsrf(string $content, string $userId): string
    {
        $pattern = '#/ui/admin/users/'.preg_quote($userId, '#').'/toggle-active.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
    }

    private function extractToggleAdminCsrf(string $content, string $userId): string
    {
        $pattern = '#/ui/admin/users/'.preg_quote($userId, '#').'/toggle-admin.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
    }

    private function extractToggleVerificationCsrf(string $content, string $userId): string
    {
        $pattern = '#/ui/admin/users/'.preg_quote($userId, '#').'/toggle-verification.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
    }

    private function extractResendVerificationCsrf(string $content, string $userId): string
    {
        $pattern = '#/ui/admin/users/'.preg_quote($userId, '#').'/resend-verification.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
    }

    private function extractResetPasswordCsrf(string $content, string $userId): string
    {
        $pattern = '#/ui/admin/users/'.preg_quote($userId, '#').'/reset-password.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
    }

    private function extractIdentityRelinkCsrf(string $content, string $identityId): string
    {
        $pattern = '#/ui/admin/identities/'.preg_quote($identityId, '#').'/relink.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
    }

    private function extractIdentityDeleteCsrf(string $content, string $identityId): string
    {
        $pattern = '#/ui/admin/identities/'.preg_quote($identityId, '#').'/delete.*?name="_token" value="([^"]+)"#s';
        self::assertMatchesRegularExpression($pattern, $content);
        preg_match($pattern, $content, $matches);
        $token = $matches[1] ?? null;
        self::assertIsString($token);
        self::assertNotSame('', $token);

        return $token;
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
