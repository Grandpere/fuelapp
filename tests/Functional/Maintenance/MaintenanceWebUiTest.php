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

use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Application\Repository\MaintenancePlannedCostRepository;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\Enum\ReminderRuleTriggerMode;
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

final class MaintenanceWebUiTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private HttpKernelInterface $httpKernel;
    private ?TerminableInterface $terminableKernel = null;
    private MaintenanceEventRepository $eventRepository;
    private MaintenancePlannedCostRepository $plannedCostRepository;

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

        $eventRepository = $container->get(MaintenanceEventRepository::class);
        if (!$eventRepository instanceof MaintenanceEventRepository) {
            throw new RuntimeException('MaintenanceEventRepository service is invalid.');
        }

        $this->eventRepository = $eventRepository;

        $plannedCostRepository = $container->get(MaintenancePlannedCostRepository::class);
        if (!$plannedCostRepository instanceof MaintenancePlannedCostRepository) {
            throw new RuntimeException('MaintenancePlannedCostRepository service is invalid.');
        }

        $this->plannedCostRepository = $plannedCostRepository;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE maintenance_planned_costs, maintenance_reminders, maintenance_reminder_rules, maintenance_events, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testUserCanAccessMaintenanceDashboard(): void
    {
        $email = 'maintenance.ui.viewer@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('Viewer Car');
        $vehicle->setPlateNumber('UI-100-AA');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-01 10:00:00'));
        $this->em->persist($vehicle);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);

        $response = $this->request('GET', '/ui/maintenance', [], [], $sessionCookie);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('Maintenance', (string) $response->getContent());
        self::assertStringContainsString('Timeline events', (string) $response->getContent());
        self::assertStringContainsString('Planner (upcoming)', (string) $response->getContent());
        self::assertStringContainsString('Attention now', (string) $response->getContent());
        self::assertStringContainsString('Due soon', (string) $response->getContent());
        self::assertStringContainsString('Recently handled', (string) $response->getContent());
        self::assertStringContainsString('Add first event', (string) $response->getContent());
        self::assertStringContainsString('Add first plan', (string) $response->getContent());
    }

    public function testUserCanCreateAndEditMaintenanceEventAndPlan(): void
    {
        $email = 'maintenance.ui.editor@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('Editor Car');
        $vehicle->setPlateNumber('UI-200-BB');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-02 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-02 10:00:00'));
        $this->em->persist($vehicle);
        $this->em->flush();

        $ownerId = $owner->getId()->toRfc4122();
        $vehicleId = $vehicle->getId()->toRfc4122();

        $sessionCookie = $this->loginWithUiForm($email, $password);

        $newEventPage = $this->request('GET', '/ui/maintenance/events/new', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $newEventPage->getStatusCode());
        $eventCsrf = $this->extractFormCsrf((string) $newEventPage->getContent());

        $createEventResponse = $this->request(
            'POST',
            '/ui/maintenance/events/new',
            [
                'vehicleId' => $vehicleId,
                'eventType' => MaintenanceEventType::SERVICE->value,
                'occurredAt' => '2026-03-02T09:30',
                'description' => 'Initial annual service',
                'odometerKilometers' => '124000',
                'totalCostEuros' => '189.90',
                'currencyCode' => 'EUR',
                '_token' => $eventCsrf,
            ],
            [],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_SEE_OTHER, $createEventResponse->getStatusCode());

        $events = iterator_to_array($this->eventRepository->allForOwner($ownerId));
        self::assertCount(1, $events);
        $eventId = $events[0]->id()->toString();

        $editEventPage = $this->request('GET', '/ui/maintenance/events/'.$eventId.'/edit', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $editEventPage->getStatusCode());
        $eventEditCsrf = $this->extractFormCsrf((string) $editEventPage->getContent());

        $editEventResponse = $this->request(
            'POST',
            '/ui/maintenance/events/'.$eventId.'/edit',
            [
                'vehicleId' => $vehicleId,
                'eventType' => MaintenanceEventType::REPAIR->value,
                'occurredAt' => '2026-03-05T18:15',
                'description' => 'Updated repair entry',
                'odometerKilometers' => '124850',
                'totalCostEuros' => '245,00',
                'currencyCode' => 'EUR',
                '_token' => $eventEditCsrf,
            ],
            [],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_SEE_OTHER, $editEventResponse->getStatusCode());

        $newPlanPage = $this->request('GET', '/ui/maintenance/plans/new', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $newPlanPage->getStatusCode());
        $planCsrf = $this->extractFormCsrf((string) $newPlanPage->getContent());

        $createPlanResponse = $this->request(
            'POST',
            '/ui/maintenance/plans/new',
            [
                'vehicleId' => $vehicleId,
                'label' => 'Front brake replacement',
                'eventType' => MaintenanceEventType::REPAIR->value,
                'plannedFor' => '2026-06-10',
                'plannedCostEuros' => '320.00',
                'currencyCode' => 'EUR',
                'notes' => 'Before summer trip',
                '_token' => $planCsrf,
            ],
            [],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_SEE_OTHER, $createPlanResponse->getStatusCode());

        $plans = iterator_to_array($this->plannedCostRepository->allForOwner($ownerId));
        self::assertCount(1, $plans);
        $planId = $plans[0]->id()->toString();

        $editPlanPage = $this->request('GET', '/ui/maintenance/plans/'.$planId.'/edit', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $editPlanPage->getStatusCode());
        $planEditCsrf = $this->extractFormCsrf((string) $editPlanPage->getContent());

        $editPlanResponse = $this->request(
            'POST',
            '/ui/maintenance/plans/'.$planId.'/edit',
            [
                'vehicleId' => $vehicleId,
                'label' => 'Front + rear brake replacement',
                'eventType' => MaintenanceEventType::REPAIR->value,
                'plannedFor' => '2026-06-15',
                'plannedCostEuros' => '470,00',
                'currencyCode' => 'EUR',
                'notes' => 'Updated budget after quote',
                '_token' => $planEditCsrf,
            ],
            [],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_SEE_OTHER, $editPlanResponse->getStatusCode());

        $dashboardResponse = $this->request('GET', '/ui/maintenance', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $dashboardResponse->getStatusCode());
        $dashboardContent = (string) $dashboardResponse->getContent();
        self::assertStringContainsString('Updated repair entry', $dashboardContent);
        self::assertStringContainsString('Front + rear brake replacement', $dashboardContent);
        self::assertStringContainsString('Handled recently', $dashboardContent);
        self::assertStringContainsString('Due soon', $dashboardContent);
    }

    public function testCreateFormsCanPreselectVehicleFromQueryString(): void
    {
        $email = 'maintenance.ui.prefill@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('Preset Car');
        $vehicle->setPlateNumber('UI-300-CC');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-03 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-03 10:00:00'));
        $this->em->persist($vehicle);
        $this->em->flush();

        $vehicleId = $vehicle->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($email, $password);

        $eventPage = $this->request('GET', '/ui/maintenance/events/new?vehicle_id='.$vehicleId.'&event_type='.MaintenanceEventType::INSPECTION->value, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $eventPage->getStatusCode());
        self::assertStringContainsString('option value="'.$vehicleId.'" selected', (string) $eventPage->getContent());
        self::assertStringContainsString('option value="'.MaintenanceEventType::INSPECTION->value.'" selected', (string) $eventPage->getContent());

        $planPage = $this->request('GET', '/ui/maintenance/plans/new?vehicle_id='.$vehicleId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $planPage->getStatusCode());
        self::assertStringContainsString('option value="'.$vehicleId.'" selected', (string) $planPage->getContent());
    }

    public function testTriggeredRemindersExposeDirectActionLinks(): void
    {
        $email = 'maintenance.ui.reminder.actions@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('Reminder Car');
        $vehicle->setPlateNumber('UI-400-DD');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-04 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-04 10:00:00'));
        $this->em->persist($vehicle);

        $rule = new MaintenanceReminderRuleEntity();
        $rule->setId(Uuid::v7());
        $rule->setOwner($owner);
        $rule->setVehicle($vehicle);
        $rule->setName('Oil service');
        $rule->setTriggerMode(ReminderRuleTriggerMode::WHICHEVER_FIRST);
        $rule->setEventType(MaintenanceEventType::SERVICE);
        $rule->setIntervalDays(180);
        $rule->setIntervalKilometers(12000);
        $rule->setCreatedAt(new DateTimeImmutable('2026-01-01 08:00:00'));
        $rule->setUpdatedAt(new DateTimeImmutable('2026-01-01 08:00:00'));
        $this->em->persist($rule);

        $reminder = new MaintenanceReminderEntity();
        $reminder->setId(Uuid::v7());
        $reminder->setOwner($owner);
        $reminder->setVehicle($vehicle);
        $reminder->setRule($rule);
        $reminder->setDedupKey('oil-service-1');
        $reminder->setDueAtDate(new DateTimeImmutable('2026-03-20 00:00:00'));
        $reminder->setDueAtOdometerKilometers(132000);
        $reminder->setDueByDate(true);
        $reminder->setDueByOdometer(true);
        $reminder->setCreatedAt(new DateTimeImmutable('2026-03-21 09:00:00'));
        $this->em->persist($reminder);

        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);

        $dashboard = $this->request('GET', '/ui/maintenance', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $dashboard->getStatusCode());
        $content = (string) $dashboard->getContent();
        $vehicleId = $vehicle->getId()->toRfc4122();
        self::assertStringContainsString('Oil service', $content);
        self::assertStringContainsString('Trigger: WHICHEVER FIRST', $content);
        self::assertStringContainsString('every 180 days', $content);
        self::assertStringContainsString('every 12000 km', $content);
        self::assertStringContainsString('/ui/maintenance/events/new?vehicle_id='.$vehicleId.'&amp;event_type=service', $content);
        self::assertStringContainsString('/ui/vehicles/'.$vehicleId, $content);
        self::assertStringContainsString('/ui/maintenance?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('/ui/receipts?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('/ui/analytics?vehicle_id='.$vehicleId, $content);
    }

    public function testMaintenanceFormsKeepVehicleFilteredReturnContext(): void
    {
        $email = 'maintenance.ui.return-context@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('Context Car');
        $vehicle->setPlateNumber('UI-700-GG');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-07 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-07 10:00:00'));
        $this->em->persist($vehicle);
        $this->em->flush();

        $vehicleId = $vehicle->getId()->toRfc4122();
        $returnTo = '/ui/maintenance?vehicle_id='.$vehicleId;
        $sessionCookie = $this->loginWithUiForm($email, $password);

        $eventPage = $this->request('GET', '/ui/maintenance/events/new?vehicle_id='.$vehicleId.'&return_to='.rawurlencode($returnTo), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $eventPage->getStatusCode());
        $eventContent = (string) $eventPage->getContent();
        self::assertStringContainsString('href="'.$returnTo.'"', $eventContent);
        self::assertStringContainsString('name="_return_to" value="'.$returnTo.'"', $eventContent);
        $eventCsrf = $this->extractFormCsrf($eventContent);

        $createEventResponse = $this->request(
            'POST',
            '/ui/maintenance/events/new',
            [
                'vehicleId' => $vehicleId,
                'eventType' => MaintenanceEventType::SERVICE->value,
                'occurredAt' => '2026-03-07T09:30',
                'description' => 'Context service',
                'odometerKilometers' => '88000',
                'totalCostEuros' => '120.00',
                'currencyCode' => 'EUR',
                '_token' => $eventCsrf,
                '_return_to' => $returnTo,
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $createEventResponse->getStatusCode());
        self::assertSame($returnTo, $createEventResponse->headers->get('Location'));

        $planPage = $this->request('GET', '/ui/maintenance/plans/new?vehicle_id='.$vehicleId.'&return_to='.rawurlencode($returnTo), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $planPage->getStatusCode());
        $planContent = (string) $planPage->getContent();
        self::assertStringContainsString('href="'.$returnTo.'"', $planContent);
        self::assertStringContainsString('name="_return_to" value="'.$returnTo.'"', $planContent);
        $planCsrf = $this->extractFormCsrf($planContent);

        $createPlanResponse = $this->request(
            'POST',
            '/ui/maintenance/plans/new',
            [
                'vehicleId' => $vehicleId,
                'label' => 'Context plan',
                'eventType' => MaintenanceEventType::SERVICE->value,
                'plannedFor' => '2026-03-20',
                'plannedCostEuros' => '240.00',
                'currencyCode' => 'EUR',
                'notes' => 'Context budget',
                '_token' => $planCsrf,
                '_return_to' => $returnTo,
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $createPlanResponse->getStatusCode());
        self::assertSame($returnTo, $createPlanResponse->headers->get('Location'));
    }

    public function testUserCanCreateEditAndDeleteReminderRuleFromFront(): void
    {
        $email = 'maintenance.ui.rules@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('Rules Car');
        $vehicle->setPlateNumber('UI-500-EE');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-05 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-05 10:00:00'));
        $this->em->persist($vehicle);
        $this->em->flush();

        $vehicleId = $vehicle->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($email, $password);

        $createPage = $this->request('GET', '/ui/maintenance/rules/new?vehicle_id='.$vehicleId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $createPage->getStatusCode());
        $createContent = (string) $createPage->getContent();
        self::assertStringContainsString('option value="'.$vehicleId.'" selected', $createContent);
        $createCsrf = $this->extractFormCsrf($createContent);

        $createResponse = $this->request(
            'POST',
            '/ui/maintenance/rules/new',
            [
                'vehicleId' => $vehicleId,
                'name' => 'Oil service',
                'triggerMode' => ReminderRuleTriggerMode::WHICHEVER_FIRST->value,
                'eventType' => MaintenanceEventType::SERVICE->value,
                'intervalDays' => '180',
                'intervalKilometers' => '12000',
                '_token' => $createCsrf,
            ],
            [],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_SEE_OTHER, $createResponse->getStatusCode());
        self::assertSame('/ui/maintenance?vehicle_id='.$vehicleId, $createResponse->headers->get('Location'));

        /** @var MaintenanceReminderRuleEntity|null $rule */
        $rule = $this->em->getRepository(MaintenanceReminderRuleEntity::class)->findOneBy(['name' => 'Oil service']);
        self::assertInstanceOf(MaintenanceReminderRuleEntity::class, $rule);
        self::assertSame(ReminderRuleTriggerMode::WHICHEVER_FIRST, $rule->getTriggerMode());

        $dashboard = $this->request('GET', '/ui/maintenance?vehicle_id='.$vehicleId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $dashboard->getStatusCode());
        $dashboardContent = (string) $dashboard->getContent();
        self::assertStringContainsString('Reminder rules', $dashboardContent);
        self::assertStringContainsString('Oil service', $dashboardContent);
        self::assertStringContainsString('Every 180 days', $dashboardContent);
        self::assertStringContainsString('Every 12000 km', $dashboardContent);

        $ruleId = $rule->getId()->toRfc4122();
        $editPage = $this->request('GET', '/ui/maintenance/rules/'.$ruleId.'/edit', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $editPage->getStatusCode());
        $editCsrf = $this->extractFormCsrf((string) $editPage->getContent());

        $editResponse = $this->request(
            'POST',
            '/ui/maintenance/rules/'.$ruleId.'/edit',
            [
                'vehicleId' => $vehicleId,
                'name' => 'Annual inspection',
                'triggerMode' => ReminderRuleTriggerMode::DATE->value,
                'eventType' => MaintenanceEventType::INSPECTION->value,
                'intervalDays' => '365',
                'intervalKilometers' => '',
                '_token' => $editCsrf,
            ],
            [],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_SEE_OTHER, $editResponse->getStatusCode());

        $this->em->clear();

        /** @var MaintenanceReminderRuleEntity|null $updatedRule */
        $updatedRule = $this->em->getRepository(MaintenanceReminderRuleEntity::class)->find($ruleId);
        self::assertInstanceOf(MaintenanceReminderRuleEntity::class, $updatedRule);
        self::assertSame('Annual inspection', $updatedRule->getName());
        self::assertSame(ReminderRuleTriggerMode::DATE, $updatedRule->getTriggerMode());
        self::assertSame(365, $updatedRule->getIntervalDays());
        self::assertNull($updatedRule->getIntervalKilometers());

        $updatedDashboard = $this->request('GET', '/ui/maintenance?vehicle_id='.$vehicleId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $updatedDashboard->getStatusCode());
        $updatedDashboardContent = (string) $updatedDashboard->getContent();
        self::assertStringContainsString('Annual inspection', $updatedDashboardContent);
        self::assertStringNotContainsString('Oil service', $updatedDashboardContent);

        $deleteToken = $this->extractDeleteToken($updatedDashboardContent, '/ui/maintenance/rules/'.$ruleId.'/delete');
        $deleteResponse = $this->request(
            'POST',
            '/ui/maintenance/rules/'.$ruleId.'/delete',
            ['_token' => $deleteToken],
            [],
            $sessionCookie,
        );

        self::assertSame(Response::HTTP_SEE_OTHER, $deleteResponse->getStatusCode());

        $this->em->clear();
        self::assertNull($this->em->getRepository(MaintenanceReminderRuleEntity::class)->find($ruleId));

        $finalDashboard = $this->request('GET', '/ui/maintenance?vehicle_id='.$vehicleId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $finalDashboard->getStatusCode());
        self::assertStringContainsString('No reminder rule yet.', (string) $finalDashboard->getContent());
    }

    public function testReminderDashboardExplainsWhyNothingIsTriggeredYet(): void
    {
        $email = 'maintenance.ui.reminder.explain@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('Explain Car');
        $vehicle->setPlateNumber('UI-600-FF');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-06 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-06 10:00:00'));
        $this->em->persist($vehicle);

        $rule = new MaintenanceReminderRuleEntity();
        $rule->setId(Uuid::v7());
        $rule->setOwner($owner);
        $rule->setVehicle($vehicle);
        $rule->setName('Brake inspection');
        $rule->setTriggerMode(ReminderRuleTriggerMode::ODOMETER);
        $rule->setEventType(MaintenanceEventType::INSPECTION);
        $rule->setIntervalKilometers(10000);
        $rule->setCreatedAt(new DateTimeImmutable('2026-03-06 10:30:00'));
        $rule->setUpdatedAt(new DateTimeImmutable('2026-03-06 10:30:00'));
        $this->em->persist($rule);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);

        $dashboard = $this->request('GET', '/ui/maintenance?vehicle_id='.$vehicle->getId()->toRfc4122(), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $dashboard->getStatusCode());
        $content = (string) $dashboard->getContent();
        self::assertStringContainsString('Brake inspection', $content);
        self::assertStringContainsString('Waiting for odometer data from a receipt or maintenance event to evaluate this rule.', $content);
        self::assertStringContainsString('No triggered reminder yet. Your rules are being tracked, but none is due right now.', $content);
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

        $csrfToken = $this->extractLoginCsrf((string) $loginPageResponse->getContent());

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

    private function extractLoginCsrf(string $content): string
    {
        self::assertMatchesRegularExpression('/name="_csrf_token" value="([^"]+)"/', $content);
        preg_match('/name="_csrf_token" value="([^"]+)"/', $content, $matches);

        $csrfToken = $matches[1] ?? null;
        self::assertIsString($csrfToken);
        self::assertNotSame('', $csrfToken);

        return $csrfToken;
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

    private function extractDeleteToken(string $content, string $actionPath): string
    {
        if (preg_match('/action="'.preg_quote($actionPath, '/').'"(?:(?!<\/form>).)*name="_token" value="([^"]+)"/s', $content, $matches)) {
            $csrfToken = $matches[1];
            self::assertNotSame('', $csrfToken);

            return $csrfToken;
        }

        self::fail(sprintf('Delete token for "%s" not found.', $actionPath));
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
