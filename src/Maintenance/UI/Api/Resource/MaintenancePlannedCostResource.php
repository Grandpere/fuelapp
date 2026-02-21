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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Maintenance\UI\Api\Resource\Input\MaintenancePlannedCostInput;
use App\Maintenance\UI\Api\Resource\Output\MaintenancePlannedCostOutput;
use App\Maintenance\UI\Api\State\MaintenancePlannedCostDeleteStateProcessor;
use App\Maintenance\UI\Api\State\MaintenancePlannedCostStateProcessor;
use App\Maintenance\UI\Api\State\MaintenancePlannedCostStateProvider;

#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/maintenance/plans', output: MaintenancePlannedCostOutput::class, provider: MaintenancePlannedCostStateProvider::class),
        new Get(
            uriTemplate: '/maintenance/plans/{id}',
            output: MaintenancePlannedCostOutput::class,
            provider: MaintenancePlannedCostStateProvider::class,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
        new Post(
            uriTemplate: '/maintenance/plans',
            input: MaintenancePlannedCostInput::class,
            output: MaintenancePlannedCostOutput::class,
            processor: MaintenancePlannedCostStateProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/maintenance/plans/{id}',
            input: MaintenancePlannedCostInput::class,
            output: MaintenancePlannedCostOutput::class,
            processor: MaintenancePlannedCostStateProcessor::class,
            read: false,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
        new Delete(
            uriTemplate: '/maintenance/plans/{id}',
            processor: MaintenancePlannedCostDeleteStateProcessor::class,
            read: false,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
    ],
)]
final class MaintenancePlannedCostResource
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    #[ApiProperty(identifier: true)]
    public ?string $id = null;
}
