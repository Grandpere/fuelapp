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

namespace App\Tests\Unit\Analytics\Application\Map;

use App\Analytics\Application\Kpi\VisitedStationPointKpi;
use App\Analytics\Application\Map\AnalyticsStationMapBuilder;
use App\PublicFuelStation\Application\Matching\PublicFuelStationMatchCandidate;
use App\PublicFuelStation\Application\Matching\PublicFuelStationMatcher;
use App\PublicFuelStation\Application\Matching\VisitedStationPublicMatchQuery;
use App\PublicFuelStation\Application\Nearby\NearbyPublicFuelStationPoint;
use App\PublicFuelStation\Application\Nearby\PublicFuelStationNearbyReader;
use LogicException;
use PHPUnit\Framework\TestCase;

final class AnalyticsStationMapBuilderTest extends TestCase
{
    public function testItBuildsVisitedAndMatchedPublicMapLayers(): void
    {
        $builder = new AnalyticsStationMapBuilder(
            new class implements PublicFuelStationMatcher {
                public function findCandidates(VisitedStationPublicMatchQuery $query): array
                {
                    throw new LogicException('Analytics map builder should use bulk matching.');
                }

                public function findBestCandidates(array $queries): array
                {
                    $matches = [];
                    foreach ($queries as $key => $query) {
                        if ('Visited A' !== $query->streetName) {
                            continue;
                        }

                        $matches[$key] = new PublicFuelStationMatchCandidate(
                            'public-1',
                            '5 PUBLIC ROAD',
                            '75001',
                            'PARIS',
                            48856120,
                            2352210,
                            32,
                            'high',
                            [
                                'gazole' => [
                                    'available' => true,
                                    'priceMilliEurosPerLiter' => 1789,
                                    'priceUpdatedAt' => '2026-04-28T09:15:00+02:00',
                                    'ruptureType' => null,
                                    'ruptureStartedAt' => null,
                                ],
                                'sp95' => [
                                    'available' => false,
                                    'priceMilliEurosPerLiter' => 1899,
                                    'priceUpdatedAt' => '2026-04-28T09:20:00+02:00',
                                    'ruptureType' => 'temporaire',
                                    'ruptureStartedAt' => '2026-04-28T10:00:00+02:00',
                                ],
                            ],
                        );
                    }

                    return $matches;
                }
            },
            new class implements PublicFuelStationNearbyReader {
                public function findNearby(array $queries, array $excludedSourceIds = [], int $limitPerQuery = 2, int $maxDistanceMeters = 2500): array
                {
                    if (['public-1'] !== $excludedSourceIds) {
                        throw new LogicException('Matched public stations should be excluded from nearby search.');
                    }

                    return [
                        'station-1' => [
                            new NearbyPublicFuelStationPoint('nearby-1', '7 NEARBY ROAD', '75001', 'PARIS', 48.8571, 2.3531, 180, ['Gazole', 'SP95']),
                        ],
                    ];
                }
            },
        );

        /** @var array{
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
         * } $map
         */
        $map = $builder->build([
            new VisitedStationPointKpi('station-1', 'Visited Alpha', 'Visited A', '75001', 'Paris', 48856100, 2352200, 3, 4200, 2100),
            new VisitedStationPointKpi('station-2', 'Visited Beta', 'Visited B', '69001', 'Lyon', 45764000, 4835700, 1, 1800, 950),
        ]);

        self::assertCount(2, $map['visitedPoints']);
        self::assertCount(1, $map['publicPoints']);
        self::assertCount(1, $map['nearbyPublicPoints']);
        self::assertSame(1, $map['matchedVisitedCount']);
        self::assertSame(1, $map['nearbyVisitedCount']);
        self::assertSame('Visited Alpha', $map['visitedPoints'][0]['stationName']);
        self::assertIsArray($map['visitedPoints'][0]['publicMatch']);
        self::assertSame('public-1', $map['visitedPoints'][0]['publicMatch']['sourceId']);
        self::assertSame(['Gazole'], $map['visitedPoints'][0]['publicMatch']['availableFuelLabels']);
        self::assertSame('nearby-1', $map['visitedPoints'][0]['nearbyPublicStations'][0]['sourceId']);
        self::assertNull($map['visitedPoints'][1]['publicMatch']);
        self::assertSame('public-1', $map['publicPoints'][0]['sourceId']);
        self::assertSame(['Visited Alpha'], $map['publicPoints'][0]['matchedStationNames']);
        self::assertSame('nearby-1', $map['nearbyPublicPoints'][0]['sourceId']);
        self::assertSame(['Visited Alpha'], $map['nearbyPublicPoints'][0]['nearbyStationNames']);
    }
}
