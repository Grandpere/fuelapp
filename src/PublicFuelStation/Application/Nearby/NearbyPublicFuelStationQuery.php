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

final readonly class NearbyPublicFuelStationQuery
{
    public function __construct(
        public int $latitudeMicroDegrees,
        public int $longitudeMicroDegrees,
    ) {
    }
}
