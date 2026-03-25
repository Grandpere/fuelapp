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

namespace App\Tests\Unit\Analytics\Infrastructure\ReadModel;

use App\Analytics\Infrastructure\ReadModel\DoctrineAnalyticsKpiReader;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class DoctrineAnalyticsKpiReaderTest extends TestCase
{
    public function testReadVisitedStationsParsesSignedCoordinateStrings(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'station_id' => 'station-1',
                    'station_name' => 'Station South West',
                    'street_name' => '42 Rue Test',
                    'postal_code' => '75000',
                    'city' => 'Paris',
                    'latitude_micro_degrees' => '-33868800',
                    'longitude_micro_degrees' => '-151209300',
                    'receipt_count' => '2',
                    'total_cost_cents' => '5000',
                    'total_quantity_milli_liters' => '30000',
                ],
            ]);

        $reader = new DoctrineAnalyticsKpiReader($connection);
        $result = $reader->readVisitedStations(
            'owner-1',
            null,
            null,
            null,
            new DateTimeImmutable('2026-01-01 00:00:00'),
            new DateTimeImmutable('2026-01-31 23:59:59'),
        );

        self::assertCount(1, $result);
        self::assertSame(-33868800, $result[0]->latitudeMicroDegrees);
        self::assertSame(-151209300, $result[0]->longitudeMicroDegrees);
    }

    public function testReadFuelDashboardSnapshotBuildsAllFuelMetricsFromSingleGroupedQuery(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'month' => '2026-01',
                    'fuel_type' => 'diesel',
                    'total_cost_cents' => '28000',
                    'total_quantity_milli_liters' => '15000',
                ],
                [
                    'month' => '2026-02',
                    'fuel_type' => 'sp95',
                    'total_cost_cents' => '34000',
                    'total_quantity_milli_liters' => '20000',
                ],
            ]);

        $reader = new DoctrineAnalyticsKpiReader($connection);
        $snapshot = $reader->readFuelDashboardSnapshot(
            'owner-1',
            null,
            null,
            null,
            new DateTimeImmutable('2026-01-01 00:00:00'),
            new DateTimeImmutable('2026-02-28 23:59:59'),
        );

        self::assertCount(2, $snapshot->costPerMonth);
        self::assertSame(28000, $snapshot->costPerMonth[0]->totalCostCents);
        self::assertSame(20000, $snapshot->consumptionPerMonth[1]->totalQuantityMilliLiters);
        self::assertCount(2, $snapshot->fuelPricePerMonth);
        self::assertSame('diesel', $snapshot->fuelPricePerMonth[0]->fuelType);
        self::assertSame(62000, $snapshot->averagePrice->totalCostCents);
        self::assertSame(35000, $snapshot->averagePrice->totalQuantityMilliLiters);
        self::assertSame(17714, $snapshot->averagePrice->averagePriceDeciCentsPerLiter);
    }
}
