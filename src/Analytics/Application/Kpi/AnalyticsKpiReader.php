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

namespace App\Analytics\Application\Kpi;

use DateTimeImmutable;

interface AnalyticsKpiReader
{
    /** @return list<MonthlyCostKpi> */
    public function readCostPerMonth(string $ownerId, ?string $vehicleId, ?string $stationId, ?string $fuelType, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array;

    /** @return list<MonthlyConsumptionKpi> */
    public function readConsumptionPerMonth(string $ownerId, ?string $vehicleId, ?string $stationId, ?string $fuelType, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array;

    public function readAveragePrice(string $ownerId, ?string $vehicleId, ?string $stationId, ?string $fuelType, ?DateTimeImmutable $from, ?DateTimeImmutable $to): AverageFuelPriceKpi;
}
