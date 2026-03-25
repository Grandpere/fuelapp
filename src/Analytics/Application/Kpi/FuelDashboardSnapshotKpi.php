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

/**
 * @phpstan-type CostItems list<MonthlyCostKpi>
 * @phpstan-type ConsumptionItems list<MonthlyConsumptionKpi>
 * @phpstan-type FuelPriceItems list<MonthlyFuelPriceKpi>
 */
final readonly class FuelDashboardSnapshotKpi
{
    /**
     * @param CostItems        $costPerMonth
     * @param ConsumptionItems $consumptionPerMonth
     * @param FuelPriceItems   $fuelPricePerMonth
     */
    public function __construct(
        public array $costPerMonth,
        public array $consumptionPerMonth,
        public array $fuelPricePerMonth,
        public AverageFuelPriceKpi $averagePrice,
    ) {
    }
}
