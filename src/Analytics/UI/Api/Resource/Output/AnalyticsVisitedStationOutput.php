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

final readonly class AnalyticsVisitedStationOutput
{
    public function __construct(
        public string $stationId,
        public string $stationName,
        public string $streetName,
        public string $postalCode,
        public string $city,
        public int $latitudeMicroDegrees,
        public int $longitudeMicroDegrees,
        public float $latitude,
        public float $longitude,
        public int $receiptCount,
        public int $totalCostCents,
        public int $totalQuantityMilliLiters,
    ) {
    }
}
