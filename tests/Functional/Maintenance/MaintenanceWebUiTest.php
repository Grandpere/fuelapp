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
                'totalCostCents' => '18990',
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
                'totalCostCents' => '24500',
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
                'plannedCostCents' => '32000',
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
                'plannedCostCents' => '47000',
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
        self::assertStringContainsString('Updated repair entry', (string) $dashboardResponse->getContent());
        self::assertStringContainsString('Front + rear brake replacement', (string) $dashboardResponse->getContent());
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
