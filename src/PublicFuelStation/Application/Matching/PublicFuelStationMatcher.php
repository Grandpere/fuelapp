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

namespace App\PublicFuelStation\Application\Matching;

interface PublicFuelStationMatcher
{
    /** @return list<PublicFuelStationMatchCandidate> */
    public function findCandidates(VisitedStationPublicMatchQuery $query): array;

    /**
     * @param array<string, VisitedStationPublicMatchQuery> $queries
     *
     * @return array<string, PublicFuelStationMatchCandidate>
     */
    public function findBestCandidates(array $queries): array;
}
