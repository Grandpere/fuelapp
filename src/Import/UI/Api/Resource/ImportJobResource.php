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
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use App\Import\UI\Api\Controller\UploadImportController;
use App\Import\UI\Api\Resource\Input\ImportFinalizeInput;
use App\Import\UI\Api\Resource\Output\ImportJobOutput;
use App\Import\UI\Api\State\ImportJobFinalizeStateProcessor;
use App\Import\UI\Api\State\ImportJobStateProvider;
use ArrayObject;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/imports',
            controller: UploadImportController::class,
            deserialize: false,
            read: false,
            validate: false,
            output: false,
            status: 201,
            openapi: new Operation(
                summary: 'Upload receipt file for async import',
                description: 'Accepts PDF/JPEG/PNG/WEBP and creates a queued import job.',
                requestBody: new RequestBody(
                    description: 'Multipart upload payload.',
                    required: true,
                    content: new ArrayObject([
                        'multipart/form-data' => new MediaType(
                            schema: new ArrayObject([
                                'type' => 'object',
                                'required' => ['file'],
                                'properties' => [
                                    'file' => ['type' => 'string', 'format' => 'binary'],
                                ],
                            ]),
                        ),
                    ]),
                ),
                responses: [
                    '201' => new Response(
                        description: 'Import job created.',
                        content: new ArrayObject([
                            'application/json' => new MediaType(
                                schema: new ArrayObject([
                                    'type' => 'object',
                                    'required' => ['id', 'status', 'createdAt'],
                                    'properties' => [
                                        'id' => ['type' => 'string', 'format' => 'uuid'],
                                        'status' => ['type' => 'string', 'enum' => ['queued']],
                                        'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                                    ],
                                ]),
                            ),
                        ]),
                    ),
                    '401' => new Response(description: 'Authentication required.'),
                    '422' => new Response(description: 'Validation failed.'),
                ],
            ),
        ),
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
