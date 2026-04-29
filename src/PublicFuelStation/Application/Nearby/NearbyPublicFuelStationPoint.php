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

namespace App\PublicFuelStation\Application\Nearby;

final readonly class NearbyPublicFuelStationPoint
{
    /** @param list<string> $availableFuelLabels */
    public function __construct(
        public string $sourceId,
        public string $address,
        public string $postalCode,
        public string $city,
        public float $latitude,
        public float $longitude,
        public int $distanceMeters,
        public array $availableFuelLabels,
    ) {
    }
}
