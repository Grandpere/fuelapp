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

namespace App\PublicFuelStation\Application\Search;

final readonly class PublicFuelStationSearchResult
{
    /** @param list<PublicFuelStationListItem> $items */
    public function __construct(
        public array $items,
        public int $totalCount,
        public int $limit,
    ) {
    }
}
