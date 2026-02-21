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

namespace App\Analytics\Infrastructure\ReadModel;

use App\Analytics\Application\Kpi\AnalyticsKpiReader;
use App\Analytics\Application\Kpi\AverageFuelPriceKpi;
use App\Analytics\Application\Kpi\MonthlyConsumptionKpi;
use App\Analytics\Application\Kpi\MonthlyCostKpi;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DoctrineAnalyticsKpiReader implements AnalyticsKpiReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function readCostPerMonth(string $ownerId, ?string $vehicleId, ?string $stationId, ?string $fuelType, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $normalizedVehicleId = $vehicleId ?? '';
        $normalizedStationId = $stationId ?? '';
        $normalizedFuelType = $fuelType ?? '';
        $normalizedFromDate = $from?->format('Y-m-d') ?? '';
        $normalizedToDate = $to?->format('Y-m-d') ?? '';

        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                    SELECT TO_CHAR(DATE_TRUNC('month', day::timestamp), 'YYYY-MM') AS month, SUM(total_cost_cents) AS total_cost_cents
                    FROM analytics_daily_fuel_kpis
                    WHERE owner_id = :ownerId
                      AND (:vehicleId = '' OR vehicle_id = CAST(NULLIF(:vehicleId, '') AS uuid))
                      AND (:stationId = '' OR station_id = CAST(NULLIF(:stationId, '') AS uuid))
                      AND (:fuelType = '' OR fuel_type = :fuelType)
                      AND (:fromDate = '' OR day >= CAST(NULLIF(:fromDate, '') AS date))
                      AND (:toDate = '' OR day <= CAST(NULLIF(:toDate, '') AS date))
                    GROUP BY DATE_TRUNC('month', day::timestamp)
                    ORDER BY DATE_TRUNC('month', day::timestamp)
                SQL,
            [
                'ownerId' => $ownerId,
                'vehicleId' => $normalizedVehicleId,
                'stationId' => $normalizedStationId,
                'fuelType' => $normalizedFuelType,
                'fromDate' => $normalizedFromDate,
                'toDate' => $normalizedToDate,
            ],
        );

        $items = [];
        foreach ($rows as $row) {
            $month = $row['month'] ?? null;
            if (!is_string($month) || '' === trim($month)) {
                continue;
            }

            $items[] = new MonthlyCostKpi($month, $this->toInt($row['total_cost_cents'] ?? null));
        }

        return $items;
    }

    public function readConsumptionPerMonth(string $ownerId, ?string $vehicleId, ?string $stationId, ?string $fuelType, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $normalizedVehicleId = $vehicleId ?? '';
        $normalizedStationId = $stationId ?? '';
        $normalizedFuelType = $fuelType ?? '';
        $normalizedFromDate = $from?->format('Y-m-d') ?? '';
        $normalizedToDate = $to?->format('Y-m-d') ?? '';

        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                    SELECT TO_CHAR(DATE_TRUNC('month', day::timestamp), 'YYYY-MM') AS month, SUM(total_quantity_milli_liters) AS total_quantity_milli_liters
                    FROM analytics_daily_fuel_kpis
                    WHERE owner_id = :ownerId
                      AND (:vehicleId = '' OR vehicle_id = CAST(NULLIF(:vehicleId, '') AS uuid))
                      AND (:stationId = '' OR station_id = CAST(NULLIF(:stationId, '') AS uuid))
                      AND (:fuelType = '' OR fuel_type = :fuelType)
                      AND (:fromDate = '' OR day >= CAST(NULLIF(:fromDate, '') AS date))
                      AND (:toDate = '' OR day <= CAST(NULLIF(:toDate, '') AS date))
                    GROUP BY DATE_TRUNC('month', day::timestamp)
                    ORDER BY DATE_TRUNC('month', day::timestamp)
                SQL,
            [
                'ownerId' => $ownerId,
                'vehicleId' => $normalizedVehicleId,
                'stationId' => $normalizedStationId,
                'fuelType' => $normalizedFuelType,
                'fromDate' => $normalizedFromDate,
                'toDate' => $normalizedToDate,
            ],
        );

        $items = [];
        foreach ($rows as $row) {
            $month = $row['month'] ?? null;
            if (!is_string($month) || '' === trim($month)) {
                continue;
            }

            $items[] = new MonthlyConsumptionKpi($month, $this->toInt($row['total_quantity_milli_liters'] ?? null));
        }

        return $items;
    }

    public function readAveragePrice(string $ownerId, ?string $vehicleId, ?string $stationId, ?string $fuelType, ?DateTimeImmutable $from, ?DateTimeImmutable $to): AverageFuelPriceKpi
    {
        $normalizedVehicleId = $vehicleId ?? '';
        $normalizedStationId = $stationId ?? '';
        $normalizedFuelType = $fuelType ?? '';
        $normalizedFromDate = $from?->format('Y-m-d') ?? '';
        $normalizedToDate = $to?->format('Y-m-d') ?? '';

        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                    SELECT
                        COALESCE(SUM(total_cost_cents), 0) AS total_cost_cents,
                        COALESCE(SUM(total_quantity_milli_liters), 0) AS total_quantity_milli_liters
                    FROM analytics_daily_fuel_kpis
                    WHERE owner_id = :ownerId
                      AND (:vehicleId = '' OR vehicle_id = CAST(NULLIF(:vehicleId, '') AS uuid))
                      AND (:stationId = '' OR station_id = CAST(NULLIF(:stationId, '') AS uuid))
                      AND (:fuelType = '' OR fuel_type = :fuelType)
                      AND (:fromDate = '' OR day >= CAST(NULLIF(:fromDate, '') AS date))
                      AND (:toDate = '' OR day <= CAST(NULLIF(:toDate, '') AS date))
                SQL,
            [
                'ownerId' => $ownerId,
                'vehicleId' => $normalizedVehicleId,
                'stationId' => $normalizedStationId,
                'fuelType' => $normalizedFuelType,
                'fromDate' => $normalizedFromDate,
                'toDate' => $normalizedToDate,
            ],
        );

        if (!is_array($row)) {
            return new AverageFuelPriceKpi(0, 0, null);
        }

        $totalCostCents = $this->toInt($row['total_cost_cents'] ?? null);
        $totalQuantityMilliLiters = $this->toInt($row['total_quantity_milli_liters'] ?? null);
        $averagePriceDeciCentsPerLiter = $totalQuantityMilliLiters > 0
            ? (int) round(($totalCostCents * 10000) / $totalQuantityMilliLiters, 0, PHP_ROUND_HALF_UP)
            : null;

        return new AverageFuelPriceKpi(
            $totalCostCents,
            $totalQuantityMilliLiters,
            $averagePriceDeciCentsPerLiter,
        );
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
