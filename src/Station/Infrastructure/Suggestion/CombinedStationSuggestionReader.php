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

namespace App\Station\Infrastructure\Suggestion;

use App\PublicFuelStation\Application\Search\PublicFuelStationSuggestion;
use App\PublicFuelStation\Application\Search\PublicFuelStationSuggestionReader;
use App\Station\Application\Search\StationSearchCandidate;
use App\Station\Application\Search\StationSearchQuery;
use App\Station\Application\Search\StationSearchReader;
use App\Station\Application\Suggestion\StationSuggestion;
use App\Station\Application\Suggestion\StationSuggestionQuery;
use App\Station\Application\Suggestion\StationSuggestionReader;

final readonly class CombinedStationSuggestionReader implements StationSuggestionReader
{
    public function __construct(
        private StationSearchReader $stationSearchReader,
        private PublicFuelStationSuggestionReader $publicSuggestionReader,
    ) {
    }

    public function search(StationSuggestionQuery $query): array
    {
        $internalSuggestions = array_map(
            fn (StationSearchCandidate $candidate): StationSuggestion => $this->mapInternalCandidate($candidate),
            $this->stationSearchReader->search(new StationSearchQuery(
                $query->freeText,
                $query->name,
                $query->streetName,
                $query->postalCode,
                $query->city,
                $query->limit,
            )),
        );

        $publicSuggestions = array_map(
            fn (PublicFuelStationSuggestion $candidate): StationSuggestion => $this->mapPublicCandidate($candidate),
            $this->publicSuggestionReader->search($query, $query->limit),
        );

        return array_merge($internalSuggestions, $publicSuggestions);
    }

    private function mapInternalCandidate(StationSearchCandidate $candidate): StationSuggestion
    {
        return new StationSuggestion(
            'station',
            $candidate->id,
            $candidate->name,
            $candidate->streetName,
            $candidate->postalCode,
            $candidate->city,
            $candidate->latitudeMicroDegrees,
            $candidate->longitudeMicroDegrees,
        );
    }

    private function mapPublicCandidate(PublicFuelStationSuggestion $candidate): StationSuggestion
    {
        return new StationSuggestion(
            'public',
            $candidate->sourceId,
            $candidate->name,
            $candidate->streetName,
            $candidate->postalCode,
            $candidate->city,
            $candidate->latitudeMicroDegrees,
            $candidate->longitudeMicroDegrees,
        );
    }
}
