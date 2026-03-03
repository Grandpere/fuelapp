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
use App\Analytics\Application\Kpi\MonthlyComparedCostKpi;
use App\Analytics\Application\Kpi\MonthlyConsumptionKpi;
use App\Analytics\Application\Kpi\MonthlyCostKpi;
use App\Analytics\Application\Kpi\MonthlyFuelPriceKpi;
use App\Analytics\Application\Kpi\VisitedStationPointKpi;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DoctrineAnalyticsKpiReader implements AnalyticsKpiReader
{
    private const string KPI_TABLE = 'analytics_daily_fuel_kpis';

    public function __construct(private Connection $connection)
    {
    }

    public function readCostPerMonth(string $ownerId, ?string $vehicleId, ?string $stationId, ?string $fuelType, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $rows = $this->fetchMonthlySumRows('total_cost_cents', $ownerId, $vehicleId, $stationId, $fuelType, $from, $to);

        $items = [];
        foreach ($rows as $row) {
            $month = $row['month'] ?? null;
            if (!is_string($month) || '' === trim($month)) {
                continue;
            }

            $items[] = new MonthlyCostKpi($month, $this->toInt($row['total_value'] ?? null));
        }

        return $items;
    }

    public function readConsumptionPerMonth(string $ownerId, ?string $vehicleId, ?string $stationId, ?string $fuelType, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $rows = $this->fetchMonthlySumRows('total_quantity_milli_liters', $ownerId, $vehicleId, $stationId, $fuelType, $from, $to);

        $items = [];
        foreach ($rows as $row) {
            $month = $row['month'] ?? null;
            if (!is_string($month) || '' === trim($month)) {
                continue;
            }

            $items[] = new MonthlyConsumptionKpi($month, $this->toInt($row['total_value'] ?? null));
        }

        return $items;
    }

    public function readAveragePrice(string $ownerId, ?string $vehicleId, ?string $stationId, ?string $fuelType, ?DateTimeImmutable $from, ?DateTimeImmutable $to): AverageFuelPriceKpi
    {
        [$whereClause, $params] = $this->buildFilters($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);

        $row = $this->connection->fetchAssociative(
            sprintf(
                <<<'SQL'
                        SELECT
                            COALESCE(SUM(total_cost_cents), 0) AS total_cost_cents,
                            COALESCE(SUM(total_quantity_milli_liters), 0) AS total_quantity_milli_liters
                        FROM %s
                        WHERE %s
                    SQL,
                self::KPI_TABLE,
                $whereClause,
            ),
            $params,
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

    public function readVisitedStations(string $ownerId, ?string $vehicleId, ?string $stationId, ?string $fuelType, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        [$whereClause, $params] = $this->buildFilters($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);

        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                <<<'SQL'
                        SELECT
                            s.id AS station_id,
                            s.name AS station_name,
                            s.street_name,
                            s.postal_code,
                            s.city,
                            s.latitude_micro_degrees,
                            s.longitude_micro_degrees,
                            COALESCE(SUM(k.receipt_count), 0) AS receipt_count,
                            COALESCE(SUM(k.total_cost_cents), 0) AS total_cost_cents,
                            COALESCE(SUM(k.total_quantity_milli_liters), 0) AS total_quantity_milli_liters
                        FROM %s k
                        INNER JOIN stations s ON s.id = k.station_id
                        WHERE %s
                          AND k.station_id IS NOT NULL
                          AND s.latitude_micro_degrees IS NOT NULL
                          AND s.longitude_micro_degrees IS NOT NULL
                        GROUP BY
                            s.id,
                            s.name,
                            s.street_name,
                            s.postal_code,
                            s.city,
                            s.latitude_micro_degrees,
                            s.longitude_micro_degrees
                        ORDER BY SUM(k.total_cost_cents) DESC, s.name ASC
                    SQL,
                self::KPI_TABLE,
                $whereClause,
            ),
            $params,
        );

        $items = [];
        foreach ($rows as $row) {
            $stationIdValue = $row['station_id'] ?? null;
            $stationNameValue = $row['station_name'] ?? null;
            $streetNameValue = $row['street_name'] ?? null;
            $postalCodeValue = $row['postal_code'] ?? null;
            $cityValue = $row['city'] ?? null;

            if (
                !is_string($stationIdValue) || '' === trim($stationIdValue)
                || !is_string($stationNameValue) || '' === trim($stationNameValue)
                || !is_string($streetNameValue) || '' === trim($streetNameValue)
                || !is_string($postalCodeValue) || '' === trim($postalCodeValue)
                || !is_string($cityValue) || '' === trim($cityValue)
            ) {
                continue;
            }

            $items[] = new VisitedStationPointKpi(
                $stationIdValue,
                $stationNameValue,
                $streetNameValue,
                $postalCodeValue,
                $cityValue,
                $this->toInt($row['latitude_micro_degrees'] ?? null),
                $this->toInt($row['longitude_micro_degrees'] ?? null),
                $this->toInt($row['receipt_count'] ?? null),
                $this->toInt($row['total_cost_cents'] ?? null),
                $this->toInt($row['total_quantity_milli_liters'] ?? null),
            );
        }

        return $items;
    }

    public function readFuelPricePerMonth(string $ownerId, ?string $vehicleId, ?string $stationId, ?string $fuelType, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        [$whereClause, $params] = $this->buildFilters($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);

        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                <<<'SQL'
                        SELECT
                            TO_CHAR(DATE_TRUNC('month', day::timestamp), 'YYYY-MM') AS month,
                            fuel_type,
                            COALESCE(SUM(total_cost_cents), 0) AS total_cost_cents,
                            COALESCE(SUM(total_quantity_milli_liters), 0) AS total_quantity_milli_liters
                        FROM %s
                        WHERE %s
                        GROUP BY DATE_TRUNC('month', day::timestamp), fuel_type
                        ORDER BY DATE_TRUNC('month', day::timestamp), fuel_type
                    SQL,
                self::KPI_TABLE,
                $whereClause,
            ),
            $params,
        );

        $items = [];
        foreach ($rows as $row) {
            $month = $row['month'] ?? null;
            $fuelTypeValue = $row['fuel_type'] ?? null;
            if (!is_string($month) || '' === trim($month) || !is_string($fuelTypeValue) || '' === trim($fuelTypeValue)) {
                continue;
            }

            $totalCostCents = $this->toInt($row['total_cost_cents'] ?? null);
            $totalQuantityMilliLiters = $this->toInt($row['total_quantity_milli_liters'] ?? null);
            $averagePriceDeciCentsPerLiter = $totalQuantityMilliLiters > 0
                ? (int) round(($totalCostCents * 10000) / $totalQuantityMilliLiters, 0, PHP_ROUND_HALF_UP)
                : null;

            $items[] = new MonthlyFuelPriceKpi(
                $month,
                $fuelTypeValue,
                $totalCostCents,
                $totalQuantityMilliLiters,
                $averagePriceDeciCentsPerLiter,
            );
        }

        return $items;
    }

    public function readComparedCostPerMonth(string $ownerId, ?string $vehicleId, ?string $stationId, ?string $fuelType, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        [$fuelWhereClause, $fuelParams] = $this->buildFilters($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);
        [$maintenanceWhereClause, $maintenanceParams] = $this->buildMaintenanceFilters($ownerId, $vehicleId, $from, $to);

        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                <<<'SQL'
                        WITH fuel AS (
                            SELECT
                                TO_CHAR(DATE_TRUNC('month', day::timestamp), 'YYYY-MM') AS month,
                                COALESCE(SUM(total_cost_cents), 0) AS fuel_cost_cents
                            FROM %s
                            WHERE %s
                            GROUP BY DATE_TRUNC('month', day::timestamp)
                        ),
                        maintenance AS (
                            SELECT
                                TO_CHAR(DATE_TRUNC('month', occurred_at), 'YYYY-MM') AS month,
                                COALESCE(SUM(total_cost_cents), 0) AS maintenance_cost_cents
                            FROM maintenance_events
                            WHERE %s
                            GROUP BY DATE_TRUNC('month', occurred_at)
                        )
                        SELECT
                            COALESCE(f.month, m.month) AS month,
                            COALESCE(f.fuel_cost_cents, 0) AS fuel_cost_cents,
                            COALESCE(m.maintenance_cost_cents, 0) AS maintenance_cost_cents
                        FROM fuel f
                        FULL OUTER JOIN maintenance m ON m.month = f.month
                        ORDER BY COALESCE(f.month, m.month)
                    SQL,
                self::KPI_TABLE,
                $fuelWhereClause,
                $maintenanceWhereClause,
            ),
            array_merge($fuelParams, $maintenanceParams),
        );

        $items = [];
        foreach ($rows as $row) {
            $month = $row['month'] ?? null;
            if (!is_string($month) || '' === trim($month)) {
                continue;
            }

            $fuelCostCents = $this->toInt($row['fuel_cost_cents'] ?? null);
            $maintenanceCostCents = $this->toInt($row['maintenance_cost_cents'] ?? null);
            $items[] = new MonthlyComparedCostKpi(
                $month,
                $fuelCostCents,
                $maintenanceCostCents,
                $fuelCostCents + $maintenanceCostCents,
            );
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchMonthlySumRows(string $column, string $ownerId, ?string $vehicleId, ?string $stationId, ?string $fuelType, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        [$whereClause, $params] = $this->buildFilters($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);

        return $this->connection->fetchAllAssociative(
            sprintf(
                <<<'SQL'
                        SELECT
                            TO_CHAR(DATE_TRUNC('month', day::timestamp), 'YYYY-MM') AS month,
                            SUM(%s) AS total_value
                        FROM %s
                        WHERE %s
                        GROUP BY DATE_TRUNC('month', day::timestamp)
                        ORDER BY DATE_TRUNC('month', day::timestamp)
                    SQL,
                $column,
                self::KPI_TABLE,
                $whereClause,
            ),
            $params,
        );
    }

    /**
     * @return array{string, array<string, scalar>}
     */
    private function buildFilters(string $ownerId, ?string $vehicleId, ?string $stationId, ?string $fuelType, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $filters = ['owner_id = :ownerId'];
        $params = ['ownerId' => $ownerId];

        if (null !== $vehicleId) {
            $filters[] = 'vehicle_id = CAST(:vehicleId AS uuid)';
            $params['vehicleId'] = $vehicleId;
        }

        if (null !== $stationId) {
            $filters[] = 'station_id = CAST(:stationId AS uuid)';
            $params['stationId'] = $stationId;
        }

        if (null !== $fuelType) {
            $filters[] = 'fuel_type = :fuelType';
            $params['fuelType'] = $fuelType;
        }

        if (null !== $from) {
            $filters[] = 'day >= :fromDate';
            $params['fromDate'] = $from->format('Y-m-d');
        }

        if (null !== $to) {
            $filters[] = 'day <= :toDate';
            $params['toDate'] = $to->format('Y-m-d');
        }

        return [implode(' AND ', $filters), $params];
    }

    /**
     * @return array{string, array<string, scalar>}
     */
    private function buildMaintenanceFilters(string $ownerId, ?string $vehicleId, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $filters = ['owner_id = :maintenanceOwnerId'];
        $params = ['maintenanceOwnerId' => $ownerId];

        if (null !== $vehicleId) {
            $filters[] = 'vehicle_id = CAST(:maintenanceVehicleId AS uuid)';
            $params['maintenanceVehicleId'] = $vehicleId;
        }

        if (null !== $from) {
            $filters[] = 'occurred_at::date >= :maintenanceFromDate';
            $params['maintenanceFromDate'] = $from->format('Y-m-d');
        }

        if (null !== $to) {
            $filters[] = 'occurred_at::date <= :maintenanceToDate';
            $params['maintenanceToDate'] = $to->format('Y-m-d');
        }

        $filters[] = 'total_cost_cents IS NOT NULL';

        return [implode(' AND ', $filters), $params];
    }

    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = trim($value);
            if ('' !== $normalized && 1 === preg_match('/^[+-]?\d+$/', $normalized)) {
                return (int) $normalized;
            }
        }

        return 0;
    }
}
