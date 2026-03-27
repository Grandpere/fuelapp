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

namespace App\Tests\Functional\Vehicle;

use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenanceEventEntity;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenancePlannedCostEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptLineEntity;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Application\Repository\VehicleRepository;
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

final class VehicleWebUiTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private HttpKernelInterface $httpKernel;
    private ?TerminableInterface $terminableKernel = null;
    private VehicleRepository $vehicleRepository;

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

        $vehicleRepository = $container->get(VehicleRepository::class);
        if (!$vehicleRepository instanceof VehicleRepository) {
            throw new RuntimeException('VehicleRepository service is invalid.');
        }
        $this->vehicleRepository = $vehicleRepository;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE maintenance_planned_costs, maintenance_reminders, maintenance_reminder_rules, maintenance_events, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testUserCanCreateEditAndDeleteVehicleFromUi(): void
    {
        $email = 'vehicle.ui.owner@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);
        $this->em->flush();

        $ownerId = $owner->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($email, $password);

        $newPage = $this->request('GET', '/ui/vehicles/new', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $newPage->getStatusCode());
        $newCsrf = $this->extractFormCsrf((string) $newPage->getContent());

        $createResponse = $this->request(
            'POST',
            '/ui/vehicles/new',
            [
                'name' => 'Family Car',
                'plateNumber' => 'AA-111-BB',
                '_token' => $newCsrf,
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $createResponse->getStatusCode());

        $vehicle = $this->vehicleRepository->findByOwnerAndPlateNumber($ownerId, 'AA-111-BB');
        self::assertNotNull($vehicle);
        $vehicleId = $vehicle->id()->toString();

        $editPage = $this->request('GET', '/ui/vehicles/'.$vehicleId.'/edit', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $editPage->getStatusCode());
        $editCsrf = $this->extractFormCsrf((string) $editPage->getContent());

        $editResponse = $this->request(
            'POST',
            '/ui/vehicles/'.$vehicleId.'/edit',
            [
                'name' => 'Family Car Updated',
                'plateNumber' => 'AA-222-CC',
                '_token' => $editCsrf,
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $editResponse->getStatusCode());

        $updated = $this->vehicleRepository->findByOwnerAndPlateNumber($ownerId, 'AA-222-CC');
        self::assertNotNull($updated);

        $listPage = $this->request('GET', '/ui/vehicles', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $listPage->getStatusCode());
        self::assertStringContainsString('Family Car Updated', (string) $listPage->getContent());
        $deleteToken = $this->extractDeleteCsrfForVehicle((string) $listPage->getContent(), $vehicleId);

        $deleteResponse = $this->request(
            'POST',
            '/ui/vehicles/'.$vehicleId.'/delete',
            [
                '_token' => $deleteToken,
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $deleteResponse->getStatusCode());

        self::assertNull($this->vehicleRepository->get($vehicleId));
    }

    public function testVehicleFormAndListKeepWorkflowContext(): void
    {
        $email = 'vehicle.ui.workflow@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);
        $this->em->flush();

        $ownerId = $owner->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($email, $password);

        $newPage = $this->request('GET', '/ui/vehicles/new?return_to='.rawurlencode('/ui/vehicles'), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $newPage->getStatusCode());
        $newContent = (string) $newPage->getContent();
        self::assertStringContainsString('href="/ui/vehicles"', $newContent);
        self::assertStringContainsString('name="_return_to" value="/ui/vehicles"', $newContent);
        $newCsrf = $this->extractFormCsrf($newContent);

        $createResponse = $this->request(
            'POST',
            '/ui/vehicles/new',
            [
                'name' => 'Workflow Car',
                'plateNumber' => 'WF-333-DD',
                '_token' => $newCsrf,
                '_return_to' => '/ui/vehicles',
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $createResponse->getStatusCode());
        self::assertSame('/ui/vehicles', $createResponse->headers->get('Location'));

        $vehicle = $this->vehicleRepository->findByOwnerAndPlateNumber($ownerId, 'WF-333-DD');
        self::assertNotNull($vehicle);
        $vehicleId = $vehicle->id()->toString();

        $listPage = $this->request('GET', '/ui/vehicles', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $listPage->getStatusCode());
        $listContent = (string) $listPage->getContent();
        self::assertStringContainsString('/ui/receipts?vehicle_id='.$vehicleId, $listContent);
        self::assertStringContainsString('/ui/maintenance?vehicle_id='.$vehicleId, $listContent);
        self::assertStringContainsString('/ui/receipts/new?vehicle_id='.$vehicleId, $listContent);
        self::assertStringContainsString('/ui/vehicles/'.$vehicleId.'/edit', $listContent);
        self::assertStringContainsString('return_to=', $listContent);
        self::assertStringNotContainsString('>Workflow</th>', $listContent);
        self::assertStringContainsString('>Open</a>', $listContent);
        self::assertStringNotContainsString('>Detail</a>', $listContent);

        $editPage = $this->request('GET', '/ui/vehicles/'.$vehicleId.'/edit?return_to='.rawurlencode('/ui/vehicles/'.$vehicleId), [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $editPage->getStatusCode());
        $editContent = (string) $editPage->getContent();
        self::assertStringContainsString('href="/ui/vehicles/'.$vehicleId.'"', $editContent);
        self::assertStringContainsString('name="_return_to" value="/ui/vehicles/'.$vehicleId.'"', $editContent);
        $editCsrf = $this->extractFormCsrf($editContent);

        $editResponse = $this->request(
            'POST',
            '/ui/vehicles/'.$vehicleId.'/edit',
            [
                'name' => 'Workflow Car Updated',
                'plateNumber' => 'WF-444-EE',
                '_token' => $editCsrf,
                '_return_to' => '/ui/vehicles/'.$vehicleId,
            ],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $editResponse->getStatusCode());
        self::assertSame('/ui/vehicles/'.$vehicleId, $editResponse->headers->get('Location'));
    }

    public function testVehicleDetailActsAsWorkflowHub(): void
    {
        $email = 'vehicle.ui.hub@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $vehicle = new \App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('Travel Car');
        $vehicle->setPlateNumber('TR-900-AA');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-10 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-10 10:00:00'));
        $this->em->persist($vehicle);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Vehicle Hub Station');
        $station->setStreetName('1 Hub Street');
        $station->setPostalCode('75010');
        $station->setCity('Paris');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setVehicle($vehicle);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-11 08:45:00'));
        $receipt->setOdometerKilometers(125400);
        $receipt->setTotalCents(2200);
        $receipt->setVatAmountCents(366);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(12000);
        $line->setUnitPriceDeciCentsPerLiter(1833);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);
        $this->em->persist($receipt);

        $event = new MaintenanceEventEntity();
        $event->setId(Uuid::v7());
        $event->setOwner($owner);
        $event->setVehicle($vehicle);
        $event->setEventType(MaintenanceEventType::SERVICE);
        $event->setOccurredAt(new DateTimeImmutable('2026-03-09 09:30:00'));
        $event->setDescription('Annual service');
        $event->setOdometerKilometers(124900);
        $event->setTotalCostCents(18990);
        $event->setCurrencyCode('EUR');
        $event->setCreatedAt(new DateTimeImmutable('2026-03-09 10:00:00'));
        $event->setUpdatedAt(new DateTimeImmutable('2026-03-09 10:00:00'));
        $this->em->persist($event);

        $plan = new MaintenancePlannedCostEntity();
        $plan->setId(Uuid::v7());
        $plan->setOwner($owner);
        $plan->setVehicle($vehicle);
        $plan->setLabel('Tyre replacement');
        $plan->setEventType(MaintenanceEventType::REPAIR);
        $plan->setPlannedFor(new DateTimeImmutable('+10 days'));
        $plan->setPlannedCostCents(32000);
        $plan->setCurrencyCode('EUR');
        $plan->setNotes('Before road trip');
        $plan->setCreatedAt(new DateTimeImmutable('2026-03-09 10:00:00'));
        $plan->setUpdatedAt(new DateTimeImmutable('2026-03-09 10:00:00'));
        $this->em->persist($plan);

        $this->em->flush();

        $vehicleId = $vehicle->getId()->toRfc4122();
        $sessionCookie = $this->loginWithUiForm($email, $password);

        $response = $this->request('GET', '/ui/vehicles/'.$vehicleId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = (string) $response->getContent();
        self::assertStringContainsString('Travel Car', $content);
        self::assertStringContainsString('Plate TR-900-AA', $content);
        self::assertStringContainsString('View receipts', $content);
        self::assertStringContainsString('/ui/receipts/new?vehicle_id='.$vehicleId, $content);
        self::assertStringNotContainsString('receipt_form_frame', $content);
        self::assertStringContainsString('/ui/receipts?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('/ui/maintenance?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('/ui/maintenance/events/new?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('/ui/maintenance/plans/new?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('Latest fuel snapshot', $content);
        self::assertStringContainsString('Maintenance watch', $content);
        self::assertStringContainsString('Quick attention point', $content);
        self::assertStringContainsString('Recent fuel spend: 22.00 EUR', $content);
        self::assertStringContainsString('/ui/analytics?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('/ui/receipts/'.$receipt->getId()->toRfc4122(), $content);
        self::assertStringContainsString('/ui/receipts/'.$receipt->getId()->toRfc4122().'/edit-metadata', $content);
        self::assertStringContainsString('return_to', $content);
        self::assertStringContainsString('Vehicle Hub Station', $content);
        self::assertStringContainsString('Annual service', $content);
        self::assertStringContainsString('/ui/maintenance/events/'.$event->getId()->toRfc4122().'/edit', $content);
        self::assertStringContainsString('Tyre replacement', $content);
        self::assertStringContainsString('/ui/maintenance/plans/'.$plan->getId()->toRfc4122().'/edit', $content);
        self::assertStringContainsString('125400 km', $content);
    }

    public function testVehicleDetailEmptyStatesExposeUsefulNextSteps(): void
    {
        $email = 'vehicle.ui.empty@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $vehicle = new \App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setName('Empty Car');
        $vehicle->setPlateNumber('EM-100-AA');
        $vehicle->setOwner($owner);
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-03-12 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-03-12 10:00:00'));
        $this->em->persist($vehicle);
        $this->em->flush();

        $sessionCookie = $this->loginWithUiForm($email, $password);
        $vehicleId = $vehicle->getId()->toRfc4122();

        $response = $this->request('GET', '/ui/vehicles/'.$vehicleId, [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = (string) $response->getContent();
        self::assertStringContainsString('No maintenance signal yet for this vehicle.', $content);
        self::assertStringContainsString('Start by adding a receipt or maintenance event for this vehicle.', $content);
        self::assertStringContainsString('No receipt linked to this vehicle yet.', $content);
        self::assertStringContainsString('No maintenance event recorded for this vehicle yet.', $content);
        self::assertStringContainsString('No upcoming maintenance plan for this vehicle.', $content);
        self::assertStringContainsString('/ui/receipts/new?vehicle_id='.$vehicleId, $content);
        self::assertStringNotContainsString('receipt_form_frame', $content);
        self::assertStringContainsString('/ui/imports', $content);
        self::assertStringContainsString('/ui/maintenance/events/new?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('/ui/maintenance/plans/new?vehicle_id='.$vehicleId, $content);
        self::assertStringContainsString('/ui/maintenance?vehicle_id='.$vehicleId, $content);
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

        self::assertMatchesRegularExpression('/name="_csrf_token" value="([^"]+)"/', (string) $loginPageResponse->getContent());
        preg_match('/name="_csrf_token" value="([^"]+)"/', (string) $loginPageResponse->getContent(), $matches);
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

    private function extractFormCsrf(string $content): string
    {
        self::assertMatchesRegularExpression('/name="_token" value="([^"]+)"/', $content);
        preg_match('/name="_token" value="([^"]+)"/', $content, $matches);

        $token = $matches[1] ?? null;
        self::assertIsString($token);

        return $token;
    }

    private function extractDeleteCsrfForVehicle(string $content, string $vehicleId): string
    {
        $pattern = '#/ui/vehicles/'.preg_quote($vehicleId, '#').'/delete.*?name="_token" value="([^"]+)"#s';
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
