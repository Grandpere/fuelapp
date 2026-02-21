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
use ApiPlatform\Metadata\Post;
use App\Admin\UI\Api\State\AdminStationDeleteProcessor;
use App\Admin\UI\Api\State\AdminStationStateProcessor;
use App\Admin\UI\Api\State\AdminStationStateProvider;
use App\Station\UI\Api\Resource\Input\UpdateStationAddressInput;
use App\Station\UI\Api\Resource\Output\StationOutput;

#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/admin/stations', output: StationOutput::class, provider: AdminStationStateProvider::class),
        new Get(
            uriTemplate: '/admin/stations/{id}',
            output: StationOutput::class,
            provider: AdminStationStateProvider::class,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
        new Post(
            uriTemplate: '/admin/stations',
            input: UpdateStationAddressInput::class,
            output: StationOutput::class,
            processor: AdminStationStateProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/admin/stations/{id}',
            input: UpdateStationAddressInput::class,
            output: StationOutput::class,
            processor: AdminStationStateProcessor::class,
            read: false,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
        new Delete(
            uriTemplate: '/admin/stations/{id}',
            processor: AdminStationDeleteProcessor::class,
            read: false,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
    ],
)]
final class AdminStationResource
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    #[ApiProperty(identifier: true)]
    public ?string $id = null;
}
