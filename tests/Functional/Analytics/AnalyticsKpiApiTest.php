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

final class AnalyticsKpiApiTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ReceiptAnalyticsProjectionRefresher $projectionRefresher;
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

        $refresher = $container->get(ReceiptAnalyticsProjectionRefresher::class);
        if (!$refresher instanceof ReceiptAnalyticsProjectionRefresher) {
            throw new RuntimeException('ReceiptAnalyticsProjectionRefresher service is invalid.');
        }
        $this->projectionRefresher = $refresher;

        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->em->getConnection()->executeStatement(
            'TRUNCATE TABLE analytics_projection_states, analytics_daily_fuel_kpis, maintenance_planned_costs, maintenance_reminders, maintenance_reminder_rules, maintenance_events, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE',
        );
    }

    public function testKpiEndpointsReturnExpectedMetricsWithFiltersAndOwnershipIsolation(): void
    {
        $owner = $this->createUser('analytics.kpi.owner@example.com', 'test1234', ['ROLE_USER']);
        $otherOwner = $this->createUser('analytics.kpi.other@example.com', 'test1234', ['ROLE_USER']);

        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setOwner($owner);
        $vehicle->setName('Analytics Car');
        $vehicle->setPlateNumber('KP-100-AA');
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-01-01 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-01-01 10:00:00'));
        $this->em->persist($vehicle);
        $this->em->flush();

        $this->createReceipt($owner, $vehicle, new DateTimeImmutable('2026-01-10 10:00:00'), [
            ['diesel', 10000, 18000, 20],
        ]);
        $this->createReceipt($owner, $vehicle, new DateTimeImmutable('2026-01-20 11:00:00'), [
            ['diesel', 5000, 20000, 20],
        ]);
        $this->createReceipt($owner, null, new DateTimeImmutable('2026-02-01 10:00:00'), [
            ['unleaded95', 20000, 17000, 20],
        ]);
        $this->createReceipt($otherOwner, null, new DateTimeImmutable('2026-01-15 12:00:00'), [
            ['diesel', 10000, 30000, 20],
        ]);
        $this->em->flush();

        $this->projectionRefresher->refresh();

        $token = $this->apiLogin('analytics.kpi.owner@example.com', 'test1234');

        $costResponse = $this->request(
            'GET',
            '/api/analytics/kpis/cost-per-month?from=2026-01-01&to=2026-02-28',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_OK, $costResponse->getStatusCode());
        $costItems = $this->extractCollectionItems(json_decode((string) $costResponse->getContent(), true, 512, JSON_THROW_ON_ERROR));
        self::assertCount(2, $costItems);
        self::assertSame('2026-01', $costItems[0]['month'] ?? null);
        self::assertSame(28000, $this->toInt($costItems[0]['totalCostCents'] ?? null));
        self::assertSame('2026-02', $costItems[1]['month'] ?? null);
        self::assertSame(34000, $this->toInt($costItems[1]['totalCostCents'] ?? null));

        $consumptionResponse = $this->request(
            'GET',
            '/api/analytics/kpis/consumption-per-month?from=2026-01-01&to=2026-02-28',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_OK, $consumptionResponse->getStatusCode());
        $consumptionItems = $this->extractCollectionItems(json_decode((string) $consumptionResponse->getContent(), true, 512, JSON_THROW_ON_ERROR));
        self::assertCount(2, $consumptionItems);
        self::assertSame(15000, $this->toInt($consumptionItems[0]['totalQuantityMilliLiters'] ?? null));
        self::assertSame(20000, $this->toInt($consumptionItems[1]['totalQuantityMilliLiters'] ?? null));

        $averageResponse = $this->request(
            'GET',
            '/api/analytics/kpis/average-price?from=2026-01-01&to=2026-02-28',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_OK, $averageResponse->getStatusCode());
        $averageItems = $this->extractCollectionItems(json_decode((string) $averageResponse->getContent(), true, 512, JSON_THROW_ON_ERROR));
        self::assertCount(1, $averageItems);
        self::assertSame(62000, $this->toInt($averageItems[0]['totalCostCents'] ?? null));
        self::assertSame(35000, $this->toInt($averageItems[0]['totalQuantityMilliLiters'] ?? null));
        self::assertSame(17714, $this->toInt($averageItems[0]['averagePriceDeciCentsPerLiter'] ?? null));

        $averageVehicleOnly = $this->request(
            'GET',
            '/api/analytics/kpis/average-price?vehicleId='.$vehicle->getId()->toRfc4122().'&from=2026-01-01&to=2026-02-28',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );
        self::assertSame(Response::HTTP_OK, $averageVehicleOnly->getStatusCode());
        $vehicleAverageItems = $this->extractCollectionItems(json_decode((string) $averageVehicleOnly->getContent(), true, 512, JSON_THROW_ON_ERROR));
        self::assertCount(1, $vehicleAverageItems);
        self::assertSame(28000, $this->toInt($vehicleAverageItems[0]['totalCostCents'] ?? null));
        self::assertSame(15000, $this->toInt($vehicleAverageItems[0]['totalQuantityMilliLiters'] ?? null));
        self::assertSame(18667, $this->toInt($vehicleAverageItems[0]['averagePriceDeciCentsPerLiter'] ?? null));
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

    /**
     * @param list<array{0:string,1:int,2:int,3:int}> $lines
     */
    private function createReceipt(UserEntity $owner, ?VehicleEntity $vehicle, DateTimeImmutable $issuedAt, array $lines): void
    {
        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation(null);
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

    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return 0;
    }
}
