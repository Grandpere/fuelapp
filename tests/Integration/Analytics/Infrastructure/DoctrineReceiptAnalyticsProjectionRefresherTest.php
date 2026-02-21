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

namespace App\Tests\Integration\Analytics\Infrastructure;

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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineReceiptAnalyticsProjectionRefresherTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private ReceiptAnalyticsProjectionRefresher $refresher;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;

        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service not found.');
        }
        $this->passwordHasher = $passwordHasher;

        $refresher = self::getContainer()->get(ReceiptAnalyticsProjectionRefresher::class);
        if (!$refresher instanceof ReceiptAnalyticsProjectionRefresher) {
            throw new RuntimeException('ReceiptAnalyticsProjectionRefresher service not found.');
        }
        $this->refresher = $refresher;

        $this->em->getConnection()->executeStatement(
            'TRUNCATE TABLE analytics_projection_states, analytics_daily_fuel_kpis, receipt_lines, receipts, vehicles, stations, users RESTART IDENTITY CASCADE',
        );
    }

    public function testRefreshBuildsDailyFuelProjectionAndFreshnessState(): void
    {
        $owner = $this->createUser('analytics.owner@example.com');
        $otherOwner = $this->createUser('analytics.other@example.com');
        $station = $this->createStation();
        $vehicle = $this->createVehicle($owner);
        $this->em->flush();

        $this->createReceipt(
            $owner,
            $station,
            $vehicle,
            new DateTimeImmutable('2026-02-01 08:00:00'),
            [
                ['diesel', 10000, 18000, 20],
                ['diesel', 5000, 18200, 20],
            ],
        );
        $this->createReceipt(
            $owner,
            $station,
            $vehicle,
            new DateTimeImmutable('2026-02-01 18:30:00'),
            [
                ['unleaded95', 20000, 17000, 20],
            ],
        );
        $this->createReceipt(
            $owner,
            null,
            null,
            new DateTimeImmutable('2026-02-02 09:10:00'),
            [
                ['diesel', 10000, 19000, 20],
            ],
        );
        $this->createReceipt(
            $otherOwner,
            null,
            null,
            new DateTimeImmutable('2026-02-01 11:00:00'),
            [
                ['diesel', 6000, 16000, 20],
            ],
        );
        $this->em->flush();

        $report = $this->refresher->refresh();
        self::assertSame(4, $report->rowsMaterialized);
        self::assertSame(4, $report->sourceReceiptCount);
        self::assertSame('2026-02-02 09:10', $report->sourceMaxIssuedAt?->format('Y-m-d H:i'));

        $dieselDayOne = $this->em->getConnection()->fetchAssociative(
            <<<'SQL'
                    SELECT receipt_count, line_count, total_cost_cents, total_quantity_milli_liters
                    FROM analytics_daily_fuel_kpis
                    WHERE owner_id = :ownerId
                      AND day = :day
                      AND vehicle_id = :vehicleId
                      AND station_id = :stationId
                      AND fuel_type = :fuelType
                SQL,
            [
                'ownerId' => $owner->getId()->toRfc4122(),
                'day' => '2026-02-01',
                'vehicleId' => $vehicle->getId()->toRfc4122(),
                'stationId' => $station->getId()->toRfc4122(),
                'fuelType' => 'diesel',
            ],
        );
        self::assertIsArray($dieselDayOne);
        self::assertSame(1, $this->toInt($dieselDayOne['receipt_count'] ?? null));
        self::assertSame(2, $this->toInt($dieselDayOne['line_count'] ?? null));
        self::assertSame(27100, $this->toInt($dieselDayOne['total_cost_cents'] ?? null));
        self::assertSame(15000, $this->toInt($dieselDayOne['total_quantity_milli_liters'] ?? null));

        $projectionState = $this->em->getConnection()->fetchAssociative(
            'SELECT status, rows_materialized, source_receipt_count, source_max_issued_at FROM analytics_projection_states WHERE projection = :projection',
            ['projection' => 'receipt_daily_fuel_kpi_v1'],
        );
        self::assertIsArray($projectionState);
        self::assertSame('fresh', $projectionState['status']);
        self::assertSame(4, $this->toInt($projectionState['rows_materialized'] ?? null));
        self::assertSame(4, $this->toInt($projectionState['source_receipt_count'] ?? null));
        $sourceMaxIssuedAt = $projectionState['source_max_issued_at'] ?? null;
        self::assertIsString($sourceMaxIssuedAt);
        self::assertSame('2026-02-02 09:10', new DateTimeImmutable($sourceMaxIssuedAt)->format('Y-m-d H:i'));
    }

    public function testRefreshRebuildsProjectionDeterministically(): void
    {
        $owner = $this->createUser('analytics.rebuild@example.com');
        $this->createReceipt(
            $owner,
            null,
            null,
            new DateTimeImmutable('2026-02-03 07:00:00'),
            [
                ['diesel', 5000, 18000, 20],
            ],
        );
        $this->em->flush();

        $first = $this->refresher->refresh();
        self::assertSame(1, $first->rowsMaterialized);

        $this->em->getConnection()->executeStatement('DELETE FROM receipts');
        $second = $this->refresher->refresh();

        self::assertSame(0, $second->rowsMaterialized);
        self::assertSame(0, $second->sourceReceiptCount);

        $rows = $this->toInt($this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM analytics_daily_fuel_kpis'));
        self::assertSame(0, $rows);
    }

    private function createUser(string $email): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'test1234'));
        $this->em->persist($user);

        return $user;
    }

    private function createStation(): StationEntity
    {
        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Analytics Station');
        $station->setStreetName('10 Rue Analytics');
        $station->setPostalCode('75001');
        $station->setCity('Paris');
        $this->em->persist($station);

        return $station;
    }

    private function createVehicle(UserEntity $owner): VehicleEntity
    {
        $vehicle = new VehicleEntity();
        $vehicle->setId(Uuid::v7());
        $vehicle->setOwner($owner);
        $vehicle->setName('Analytics Car');
        $vehicle->setPlateNumber('AN-123-LT');
        $vehicle->setCreatedAt(new DateTimeImmutable('2026-01-01 10:00:00'));
        $vehicle->setUpdatedAt(new DateTimeImmutable('2026-01-01 10:00:00'));
        $this->em->persist($vehicle);

        return $vehicle;
    }

    /**
     * @param list<array{0:string,1:int,2:int,3:int}> $lines
     */
    private function createReceipt(
        UserEntity $owner,
        ?StationEntity $station,
        ?VehicleEntity $vehicle,
        DateTimeImmutable $issuedAt,
        array $lines,
    ): void {
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
