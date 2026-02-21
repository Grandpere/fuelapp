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
use App\Analytics\UI\Api\Resource\Output\AnalyticsAveragePriceOutput;
use App\Analytics\UI\Api\State\AnalyticsAveragePriceStateProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/analytics/kpis/average-price',
            output: AnalyticsAveragePriceOutput::class,
            provider: AnalyticsAveragePriceStateProvider::class,
        ),
    ],
)]
final class AnalyticsAveragePriceResource
{
    #[ApiProperty(identifier: true)]
    public string $id = 'average';
}
