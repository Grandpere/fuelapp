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

namespace App\Import\UI\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Import\UI\Api\Resource\Input\ImportFinalizeInput;
use App\Import\UI\Api\Resource\Output\ImportJobOutput;
use App\Import\UI\Api\State\ImportJobFinalizeStateProcessor;
use App\Import\UI\Api\State\ImportJobStateProvider;

#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/imports', output: ImportJobOutput::class, provider: ImportJobStateProvider::class),
        new Get(
            uriTemplate: '/imports/{id}',
            output: ImportJobOutput::class,
            provider: ImportJobStateProvider::class,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
        new Post(
            uriTemplate: '/imports/{id}/finalize',
            input: ImportFinalizeInput::class,
            output: ImportJobOutput::class,
            processor: ImportJobFinalizeStateProcessor::class,
            status: 200,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
    ],
)]
final class ImportJobResource
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    #[ApiProperty(identifier: true)]
    public ?string $id = null;
}
