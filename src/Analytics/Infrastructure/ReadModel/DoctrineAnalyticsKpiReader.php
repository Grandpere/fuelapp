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
