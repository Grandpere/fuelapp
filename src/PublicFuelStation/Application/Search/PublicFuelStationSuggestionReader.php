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

use App\Station\Application\Suggestion\StationSuggestionQuery;

interface PublicFuelStationSuggestionReader
{
    /** @return list<PublicFuelStationSuggestion> */
    public function search(StationSuggestionQuery $query, int $limit): array;

    public function getBySourceId(string $sourceId): ?PublicFuelStationSuggestion;
}
