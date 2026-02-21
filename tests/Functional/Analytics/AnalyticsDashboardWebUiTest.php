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

namespace App\Tests\Functional\Analytics;

use App\Analytics\Application\Aggregation\ReceiptAnalyticsProjectionRefresher;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptLineEntity;
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

final class AnalyticsDashboardWebUiTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private HttpKernelInterface $httpKernel;
    private ?TerminableInterface $terminableKernel = null;
    private ReceiptAnalyticsProjectionRefresher $projectionRefresher;

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

        $refresher = $container->get(ReceiptAnalyticsProjectionRefresher::class);
        if (!$refresher instanceof ReceiptAnalyticsProjectionRefresher) {
            throw new RuntimeException('ReceiptAnalyticsProjectionRefresher service is invalid.');
        }
        $this->projectionRefresher = $refresher;

        $this->em->getConnection()->executeStatement(
            'TRUNCATE TABLE analytics_projection_states, analytics_daily_fuel_kpis, maintenance_planned_costs, maintenance_reminders, maintenance_reminder_rules, maintenance_events, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE',
        );
    }

    public function testUserCanViewAnalyticsDashboardWithFiltersAndTrends(): void
    {
        $owner = $this->createUser('analytics.web.owner@example.com', 'test1234', ['ROLE_USER']);
        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setOwner($owner);
        $vehicle->setName('Trend Car');
        $vehicle->setPlateNumber('TR-100-AA');
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-01-01 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-01-01 10:00:00'));
        $this->em->persist($vehicle);

        $stationA = $this->createStation('Station A', '1 Main St', '75001', 'Paris');
        $stationB = $this->createStation('Station B', '2 Oak Ave', '69001', 'Lyon');
        $this->em->flush();

        $this->createReceipt($owner, $vehicle, $stationA, new DateTimeImmutable('2026-01-10 10:00:00'), [
            ['diesel', 10000, 18000, 20],
        ]);
        $this->createReceipt($owner, $vehicle, $stationA, new DateTimeImmutable('2026-01-20 10:00:00'), [
            ['diesel', 5000, 20000, 20],
        ]);
        $this->createReceipt($owner, null, $stationB, new DateTimeImmutable('2026-02-01 10:00:00'), [
            ['unleaded95', 20000, 17000, 20],
        ]);
        $this->em->flush();

        $this->projectionRefresher->refresh();
        $sessionCookie = $this->loginWithUiForm('analytics.web.owner@example.com', 'test1234');

        $response = $this->request('GET', '/ui/analytics?from=2026-01-01&to=2026-02-28', [], [], $sessionCookie);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = (string) $response->getContent();
        self::assertStringContainsString('Analytics Dashboard', $content);
        self::assertStringContainsString('Total cost', $content);
        self::assertStringContainsString('620.00 EUR', $content);
        self::assertStringContainsString('35.00 L', $content);
        self::assertStringContainsString('17.714 EUR/L', $content);
        self::assertStringContainsString('2026-01', $content);
        self::assertStringContainsString('280.00 EUR', $content);
        self::assertStringContainsString('2026-02', $content);
        self::assertStringContainsString('20.00 L', $content);

        $vehicleResponse = $this->request(
            'GET',
            '/ui/analytics?vehicle_id='.$vehicle->getId()->toRfc4122().'&from=2026-01-01&to=2026-02-28',
            [],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_OK, $vehicleResponse->getStatusCode());
        $vehicleContent = (string) $vehicleResponse->getContent();
        self::assertStringContainsString('280.00 EUR', $vehicleContent);
        self::assertStringContainsString('15.00 L', $vehicleContent);
        self::assertStringContainsString('18.667 EUR/L', $vehicleContent);

        $stationFuelResponse = $this->request(
            'GET',
            '/ui/analytics?station_id='.$stationA->getId()->toRfc4122().'&fuel_type=diesel&from=2026-01-01&to=2026-02-28',
            [],
            [],
            $sessionCookie,
        );
        self::assertSame(Response::HTTP_OK, $stationFuelResponse->getStatusCode());
        $stationFuelContent = (string) $stationFuelResponse->getContent();
        self::assertStringContainsString('280.00 EUR', $stationFuelContent);
        self::assertStringContainsString('15.00 L', $stationFuelContent);
        self::assertStringContainsString('18.667 EUR/L', $stationFuelContent);
        self::assertStringContainsString('All stations', $stationFuelContent);
        self::assertStringContainsString('All fuel types', $stationFuelContent);
        self::assertStringContainsString('station_id='.$stationA->getId()->toRfc4122(), $stationFuelContent);
        self::assertStringContainsString('fuel_type=diesel', $stationFuelContent);
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

    private function createStation(string $name, string $street, string $postalCode, string $city): StationEntity
    {
        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName($name);
        $station->setStreetName($street);
        $station->setPostalCode($postalCode);
        $station->setCity($city);
        $this->em->persist($station);

        return $station;
    }

    /**
     * @param list<array{0:string,1:int,2:int,3:int}> $lines
     */
    private function createReceipt(UserEntity $owner, ?VehicleEntity $vehicle, ?StationEntity $station, DateTimeImmutable $issuedAt, array $lines): void
    {
        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setVehicle($vehicle);
        $receipt->setIssuedAt($issuedAt);
        $receipt->setTotalCents(0);
        $receipt->setVatAmountCents(0);

        $total = 0;
        $totalVat = 0;
        foreach ($lines as [$fuelType, $quantityMilliLiters, $unitPriceDeciCentsPerLiter, $vatRatePercent]) {
            $line = new ReceiptLineEntity();
            $line->setId(Uuid::v7());
            $line->setFuelType($fuelType);
            $line->setQuantityMilliLiters($quantityMilliLiters);
            $line->setUnitPriceDeciCentsPerLiter($unitPriceDeciCentsPerLiter);
            $line->setVatRatePercent($vatRatePercent);
            $receipt->addLine($line);

            $lineTotal = (int) round(($unitPriceDeciCentsPerLiter * $quantityMilliLiters) / 10000, 0, PHP_ROUND_HALF_UP);
            $total += $lineTotal;
            $totalVat += (int) round($lineTotal * $vatRatePercent / (100 + $vatRatePercent), 0, PHP_ROUND_HALF_UP);
        }

        $receipt->setTotalCents($total);
        $receipt->setVatAmountCents($totalVat);
        $this->em->persist($receipt);
    }
}
