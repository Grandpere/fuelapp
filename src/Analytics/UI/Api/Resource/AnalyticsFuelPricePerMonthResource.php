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
use App\Analytics\UI\Api\Resource\Output\AnalyticsFuelPricePerMonthOutput;
use App\Analytics\UI\Api\State\AnalyticsFuelPricePerMonthStateProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/analytics/kpis/fuel-price-per-month',
            output: AnalyticsFuelPricePerMonthOutput::class,
            provider: AnalyticsFuelPricePerMonthStateProvider::class,
        ),
    ],
)]
final class AnalyticsFuelPricePerMonthResource
{
    #[ApiProperty(identifier: true)]
    public ?string $id = null;
}
