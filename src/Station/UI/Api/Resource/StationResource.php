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

namespace App\Station\UI\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Station\UI\Api\Resource\Output\StationOutput;
use App\Station\UI\Api\State\StationDeleteStateProcessor;
use App\Station\UI\Api\State\StationStateProvider;

#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/stations', output: StationOutput::class, provider: StationStateProvider::class),
        new Get(
            uriTemplate: '/stations/{id}',
            output: StationOutput::class,
            provider: StationStateProvider::class,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
        new Delete(
            uriTemplate: '/stations/{id}',
            processor: StationDeleteStateProcessor::class,
            read: false,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
    ],
)]
final class StationResource
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    #[ApiProperty(identifier: true)]
    public ?string $id = null;
}
