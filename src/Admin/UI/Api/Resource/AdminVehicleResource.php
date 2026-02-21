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

namespace App\Admin\UI\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Admin\UI\Api\Resource\Input\AdminVehicleInput;
use App\Admin\UI\Api\Resource\Output\AdminVehicleOutput;
use App\Admin\UI\Api\State\AdminVehicleDeleteProcessor;
use App\Admin\UI\Api\State\AdminVehicleStateProcessor;
use App\Admin\UI\Api\State\AdminVehicleStateProvider;

#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/admin/vehicles', output: AdminVehicleOutput::class, provider: AdminVehicleStateProvider::class),
        new Get(
            uriTemplate: '/admin/vehicles/{id}',
            output: AdminVehicleOutput::class,
            provider: AdminVehicleStateProvider::class,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
        new Patch(
            uriTemplate: '/admin/vehicles/{id}',
            input: AdminVehicleInput::class,
            output: AdminVehicleOutput::class,
            processor: AdminVehicleStateProcessor::class,
            read: false,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
        new Delete(
            uriTemplate: '/admin/vehicles/{id}',
            processor: AdminVehicleDeleteProcessor::class,
            read: false,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
    ],
)]
final class AdminVehicleResource
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    #[ApiProperty(identifier: true)]
    public ?string $id = null;
}
