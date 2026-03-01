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

namespace App\Analytics\UI\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Analytics\UI\Api\Resource\Output\AnalyticsVisitedStationOutput;
use App\Analytics\UI\Api\State\AnalyticsVisitedStationsStateProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/analytics/stations/visited',
            output: AnalyticsVisitedStationOutput::class,
            provider: AnalyticsVisitedStationsStateProvider::class,
        ),
    ],
)]
final class AnalyticsVisitedStationsResource
{
    #[ApiProperty(identifier: true)]
    public ?string $stationId = null;
}
