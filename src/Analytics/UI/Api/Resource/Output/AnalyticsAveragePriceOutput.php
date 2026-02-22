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

namespace App\Analytics\UI\Api\Resource\Output;

use DateTimeImmutable;

final readonly class AnalyticsAveragePriceOutput
{
    public function __construct(
        public string $id,
        public ?string $vehicleId,
        public ?DateTimeImmutable $from,
        public ?DateTimeImmutable $to,
        public int $totalCostCents,
        public int $totalQuantityMilliLiters,
        public ?int $averagePriceDeciCentsPerLiter,
        public string $priceUnit,
    ) {
    }
}
