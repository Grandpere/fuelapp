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
use App\Analytics\UI\Api\Resource\Output\AnalyticsConsumptionPerMonthOutput;
use App\Analytics\UI\Api\State\AnalyticsConsumptionPerMonthStateProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/analytics/kpis/consumption-per-month',
            output: AnalyticsConsumptionPerMonthOutput::class,
            provider: AnalyticsConsumptionPerMonthStateProvider::class,
        ),
    ],
)]
final class AnalyticsConsumptionPerMonthResource
{
    #[ApiProperty(identifier: true)]
    public ?string $month = null;
}
