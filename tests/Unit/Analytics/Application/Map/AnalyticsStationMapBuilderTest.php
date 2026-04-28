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
use PHPUnit\Framework\TestCase;

final class AnalyticsStationMapBuilderTest extends TestCase
{
    public function testItBuildsVisitedAndMatchedPublicMapLayers(): void
    {
        $builder = new AnalyticsStationMapBuilder(new class implements PublicFuelStationMatcher {
            public function findCandidates(VisitedStationPublicMatchQuery $query): array
            {
                if ('Visited A' === $query->streetName) {
                    return [
                        new PublicFuelStationMatchCandidate(
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
                        ),
                    ];
                }

                return [];
            }
        });

        $map = $builder->build([
            new VisitedStationPointKpi('station-1', 'Visited Alpha', 'Visited A', '75001', 'Paris', 48856100, 2352200, 3, 4200, 2100),
            new VisitedStationPointKpi('station-2', 'Visited Beta', 'Visited B', '69001', 'Lyon', 45764000, 4835700, 1, 1800, 950),
        ]);

        self::assertCount(2, $map['visitedPoints']);
        self::assertCount(1, $map['publicPoints']);
        self::assertSame(1, $map['matchedVisitedCount']);
        self::assertSame('Visited Alpha', $map['visitedPoints'][0]['stationName']);
        self::assertIsArray($map['visitedPoints'][0]['publicMatch']);
        self::assertSame('public-1', $map['visitedPoints'][0]['publicMatch']['sourceId']);
        self::assertSame(['Gazole'], $map['visitedPoints'][0]['publicMatch']['availableFuelLabels']);
        self::assertNull($map['visitedPoints'][1]['publicMatch']);
        self::assertSame('public-1', $map['publicPoints'][0]['sourceId']);
        self::assertSame(['Visited Alpha'], $map['publicPoints'][0]['matchedStationNames']);
    }
}
