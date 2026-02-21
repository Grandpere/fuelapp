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
use ApiPlatform\Metadata\Post;
use App\Admin\UI\Api\State\AdminImportJobFinalizeStateProcessor;
use App\Admin\UI\Api\State\AdminImportJobRetryStateProcessor;
use App\Import\UI\Api\Resource\Input\ImportFinalizeInput;
use App\Import\UI\Api\Resource\Output\ImportJobOutput;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/imports/{id}/retry',
            output: ImportJobOutput::class,
            processor: AdminImportJobRetryStateProcessor::class,
            status: 200,
            read: false,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
        new Post(
            uriTemplate: '/admin/imports/{id}/finalize',
            input: ImportFinalizeInput::class,
            output: ImportJobOutput::class,
            processor: AdminImportJobFinalizeStateProcessor::class,
            status: 200,
            read: false,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
    ],
)]
final class AdminImportJobActionResource
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    #[ApiProperty(identifier: true)]
    public ?string $id = null;
}
