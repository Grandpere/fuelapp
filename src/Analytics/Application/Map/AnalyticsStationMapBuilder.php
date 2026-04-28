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
use App\PublicFuelStation\Domain\Enum\PublicFuelType;

final readonly class AnalyticsStationMapBuilder
{
    public function __construct(private PublicFuelStationMatcher $publicFuelStationMatcher)
    {
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
     *         }
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
     *     matchedVisitedCount:int
     * }
     */
    public function build(array $items): array
    {
        $visitedPoints = [];
        $publicPoints = [];
        $matchedVisitedCount = 0;
        $matches = $this->bestMatches($items);

        foreach ($items as $item) {
            $match = $matches[$item->stationId] ?? null;
            if (null !== $match) {
                ++$matchedVisitedCount;
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
            ];

            if (null === $match || null === $match->latitudeMicroDegrees || null === $match->longitudeMicroDegrees) {
                continue;
            }

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

        foreach ($publicPoints as &$publicPoint) {
            $publicPoint['matchedStationNames'] = array_keys(array_fill_keys($publicPoint['matchedStationNames'], true));
        }
        unset($publicPoint);

        return [
            'visitedPoints' => $visitedPoints,
            'publicPoints' => array_values($publicPoints),
            'matchedVisitedCount' => $matchedVisitedCount,
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
