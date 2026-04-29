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

namespace App\Analytics\Application\Map;

use App\Analytics\Application\Kpi\VisitedStationPointKpi;
use App\PublicFuelStation\Application\Matching\PublicFuelStationMatchCandidate;
use App\PublicFuelStation\Application\Matching\PublicFuelStationMatcher;
use App\PublicFuelStation\Application\Matching\VisitedStationPublicMatchQuery;
use App\PublicFuelStation\Application\Nearby\NearbyPublicFuelStationPoint;
use App\PublicFuelStation\Application\Nearby\NearbyPublicFuelStationQuery;
use App\PublicFuelStation\Application\Nearby\PublicFuelStationNearbyReader;
use App\PublicFuelStation\Domain\Enum\PublicFuelType;

final readonly class AnalyticsStationMapBuilder
{
    public function __construct(
        private PublicFuelStationMatcher $publicFuelStationMatcher,
        private PublicFuelStationNearbyReader $publicFuelStationNearbyReader,
    ) {
    }

    /**
     * @param list<VisitedStationPointKpi> $items
     *
     * @return array{
     *     visitedPoints:list<array{
     *         stationId:string,
     *         stationName:string,
     *         address:string,
     *         latitude:float,
     *         longitude:float,
     *         receiptCount:int,
     *         totalCostCents:int,
     *         totalQuantityMilliLiters:int,
     *         publicMatch:?array{
     *             sourceId:string,
     *             address:string,
     *             city:string,
     *             postalCode:string,
     *             confidence:string,
     *             distanceMeters:?int,
     *             latitude:?float,
     *             longitude:?float,
     *             availableFuelLabels:list<string>
     *         },
     *         nearbyPublicStations:list<array{
     *             sourceId:string,
     *             address:string,
     *             city:string,
     *             postalCode:string,
     *             distanceMeters:int,
     *             latitude:float,
     *             longitude:float,
     *             availableFuelLabels:list<string>
     *         }>
     *     }>,
     *     publicPoints:list<array{
     *         sourceId:string,
     *         address:string,
     *         city:string,
     *         postalCode:string,
     *         confidence:string,
     *         distanceMeters:?int,
     *         latitude:float,
     *         longitude:float,
     *         availableFuelLabels:list<string>,
     *         matchedStationNames:list<string>
     *     }>,
     *     nearbyPublicPoints:list<array{
     *         sourceId:string,
     *         address:string,
     *         city:string,
     *         postalCode:string,
     *         distanceMeters:int,
     *         latitude:float,
     *         longitude:float,
     *         availableFuelLabels:list<string>,
     *         nearbyStationNames:list<string>
     *     }>,
     *     matchedVisitedCount:int,
     *     nearbyVisitedCount:int
     * }
     */
    public function build(array $items): array
    {
        $visitedPoints = [];
        $publicPoints = [];
        $nearbyPublicPoints = [];
        $matchedVisitedCount = 0;
        $matches = $this->bestMatches($items);
        $nearbyByStation = $this->nearbyPoints($items, $matches);
        $nearbyVisitedCount = 0;

        foreach ($items as $item) {
            $match = $matches[$item->stationId] ?? null;
            $nearbyStations = $nearbyByStation[$item->stationId] ?? [];
            if (null !== $match) {
                ++$matchedVisitedCount;
            }
            if ([] !== $nearbyStations) {
                ++$nearbyVisitedCount;
            }

            $visitedPoints[] = [
                'stationId' => $item->stationId,
                'stationName' => $item->stationName,
                'address' => sprintf('%s, %s %s', $item->streetName, $item->postalCode, $item->city),
                'latitude' => $item->latitudeMicroDegrees / 1_000_000.0,
                'longitude' => $item->longitudeMicroDegrees / 1_000_000.0,
                'receiptCount' => $item->receiptCount,
                'totalCostCents' => $item->totalCostCents,
                'totalQuantityMilliLiters' => $item->totalQuantityMilliLiters,
                'publicMatch' => $this->mapMatch($match),
                'nearbyPublicStations' => array_map(fn (NearbyPublicFuelStationPoint $point): array => $this->mapNearbyPoint($point), $nearbyStations),
            ];

            if (null === $match || null === $match->latitudeMicroDegrees || null === $match->longitudeMicroDegrees) {
            } else {
                $sourceId = $match->sourceId;
                if (!isset($publicPoints[$sourceId])) {
                    $publicPoints[$sourceId] = [
                        'sourceId' => $sourceId,
                        'address' => $match->address,
                        'city' => $match->city,
                        'postalCode' => $match->postalCode,
                        'confidence' => $match->confidence,
                        'distanceMeters' => $match->distanceMeters,
                        'latitude' => $match->latitudeMicroDegrees / 1_000_000.0,
                        'longitude' => $match->longitudeMicroDegrees / 1_000_000.0,
                        'availableFuelLabels' => $this->availableFuelLabels($match),
                        'matchedStationNames' => [],
                    ];
                }

                $publicPoints[$sourceId]['matchedStationNames'][] = $item->stationName;
            }

            foreach ($nearbyStations as $nearbyPoint) {
                $sourceId = $nearbyPoint->sourceId;
                if (!isset($nearbyPublicPoints[$sourceId])) {
                    $nearbyPublicPoints[$sourceId] = [
                        'sourceId' => $sourceId,
                        'address' => $nearbyPoint->address,
                        'city' => $nearbyPoint->city,
                        'postalCode' => $nearbyPoint->postalCode,
                        'distanceMeters' => $nearbyPoint->distanceMeters,
                        'latitude' => $nearbyPoint->latitude,
                        'longitude' => $nearbyPoint->longitude,
                        'availableFuelLabels' => $nearbyPoint->availableFuelLabels,
                        'nearbyStationNames' => [],
                    ];
                }

                $nearbyPublicPoints[$sourceId]['nearbyStationNames'][] = $item->stationName;
                $nearbyPublicPoints[$sourceId]['distanceMeters'] = min($nearbyPublicPoints[$sourceId]['distanceMeters'], $nearbyPoint->distanceMeters);
            }
        }

        foreach ($publicPoints as &$publicPoint) {
            $publicPoint['matchedStationNames'] = array_keys(array_fill_keys($publicPoint['matchedStationNames'], true));
        }
        unset($publicPoint);

        foreach ($nearbyPublicPoints as &$nearbyPublicPoint) {
            $nearbyPublicPoint['nearbyStationNames'] = array_keys(array_fill_keys($nearbyPublicPoint['nearbyStationNames'], true));
        }
        unset($nearbyPublicPoint);

        return [
            'visitedPoints' => $visitedPoints,
            'publicPoints' => array_values($publicPoints),
            'nearbyPublicPoints' => array_values($nearbyPublicPoints),
            'matchedVisitedCount' => $matchedVisitedCount,
            'nearbyVisitedCount' => $nearbyVisitedCount,
        ];
    }

    /**
     * @param list<VisitedStationPointKpi> $items
     *
     * @return array<string, PublicFuelStationMatchCandidate>
     */
    private function bestMatches(array $items): array
    {
        $queries = [];
        foreach ($items as $item) {
            $queries[$item->stationId] = new VisitedStationPublicMatchQuery(
                $item->latitudeMicroDegrees,
                $item->longitudeMicroDegrees,
                $item->streetName,
                $item->postalCode,
                $item->city,
                1,
            );
        }

        return $this->publicFuelStationMatcher->findBestCandidates($queries);
    }

    /**
     * @param list<VisitedStationPointKpi>                   $items
     * @param array<string, PublicFuelStationMatchCandidate> $matches
     *
     * @return array<string, list<NearbyPublicFuelStationPoint>>
     */
    private function nearbyPoints(array $items, array $matches): array
    {
        $queries = [];
        $excludedSourceIds = [];

        foreach ($items as $item) {
            $queries[$item->stationId] = new NearbyPublicFuelStationQuery(
                $item->latitudeMicroDegrees,
                $item->longitudeMicroDegrees,
            );

            $match = $matches[$item->stationId] ?? null;
            if ($match instanceof PublicFuelStationMatchCandidate) {
                $excludedSourceIds[] = $match->sourceId;
            }
        }

        return $this->publicFuelStationNearbyReader->findNearby($queries, $excludedSourceIds);
    }

    /**
     * @return ?array{
     *     sourceId:string,
     *     address:string,
     *     city:string,
     *     postalCode:string,
     *     confidence:string,
     *     distanceMeters:?int,
     *     latitude:?float,
     *     longitude:?float,
     *     availableFuelLabels:list<string>
     * }
     */
    private function mapMatch(?PublicFuelStationMatchCandidate $match): ?array
    {
        if (null === $match) {
            return null;
        }

        return [
            'sourceId' => $match->sourceId,
            'address' => $match->address,
            'city' => $match->city,
            'postalCode' => $match->postalCode,
            'confidence' => $match->confidence,
            'distanceMeters' => $match->distanceMeters,
            'latitude' => null !== $match->latitudeMicroDegrees ? $match->latitudeMicroDegrees / 1_000_000.0 : null,
            'longitude' => null !== $match->longitudeMicroDegrees ? $match->longitudeMicroDegrees / 1_000_000.0 : null,
            'availableFuelLabels' => $this->availableFuelLabels($match),
        ];
    }

    /**
     * @return array{
     *     sourceId:string,
     *     address:string,
     *     city:string,
     *     postalCode:string,
     *     distanceMeters:int,
     *     latitude:float,
     *     longitude:float,
     *     availableFuelLabels:list<string>
     * }
     */
    private function mapNearbyPoint(NearbyPublicFuelStationPoint $point): array
    {
        return [
            'sourceId' => $point->sourceId,
            'address' => $point->address,
            'city' => $point->city,
            'postalCode' => $point->postalCode,
            'distanceMeters' => $point->distanceMeters,
            'latitude' => $point->latitude,
            'longitude' => $point->longitude,
            'availableFuelLabels' => $point->availableFuelLabels,
        ];
    }

    /** @return list<string> */
    private function availableFuelLabels(PublicFuelStationMatchCandidate $match): array
    {
        $labels = [];
        foreach ($match->fuels as $fuel => $snapshot) {
            if (true !== ($snapshot['available'] ?? false)) {
                continue;
            }

            $labels[] = PublicFuelType::tryFrom($fuel)?->sourceLabel() ?? strtoupper($fuel);
        }

        sort($labels);

        return $labels;
    }
}
