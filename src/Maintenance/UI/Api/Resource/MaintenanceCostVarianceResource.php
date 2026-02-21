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

namespace App\Maintenance\UI\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Maintenance\UI\Api\Resource\Output\MaintenanceCostVarianceOutput;
use App\Maintenance\UI\Api\State\MaintenanceCostVarianceStateProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/maintenance/cost-variance',
            output: MaintenanceCostVarianceOutput::class,
            provider: MaintenanceCostVarianceStateProvider::class,
        ),
    ],
)]
final class MaintenanceCostVarianceResource
{
    #[ApiProperty(identifier: true)]
    public ?string $vehicleId = null;
}
