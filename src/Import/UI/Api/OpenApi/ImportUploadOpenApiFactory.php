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

namespace App\Import\UI\Api\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\Model\SecurityScheme;
use ApiPlatform\OpenApi\OpenApi;
use ArrayObject;

final readonly class ImportUploadOpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(private OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $uploadOperation = new Operation(
            operationId: 'api_import_upload',
            tags: ['Import'],
            responses: [
                '201' => new Response(
                    description: 'Import job created.',
                    content: new ArrayObject([
                        'application/json' => new MediaType(new ArrayObject([
                            'type' => 'object',
                            'required' => ['id', 'status', 'createdAt'],
                            'properties' => [
                                'id' => ['type' => 'string', 'format' => 'uuid'],
                                'status' => ['type' => 'string', 'enum' => ['queued']],
                                'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                            ],
                        ])),
                    ]),
                ),
                '401' => new Response(description: 'Authentication required.'),
                '422' => new Response(description: 'Validation failed.'),
            ],
            summary: 'Upload receipt file for async import',
            description: 'Accepts PDF/JPEG/PNG/WEBP and creates a queued import job.',
            requestBody: new RequestBody(
                description: 'Multipart upload payload.',
                content: new ArrayObject([
                    'multipart/form-data' => new MediaType(new ArrayObject([
                        'type' => 'object',
                        'required' => ['file'],
                        'properties' => [
                            'file' => ['type' => 'string', 'format' => 'binary'],
                        ],
                    ])),
                ]),
                required: true,
            ),
            security: [['bearerAuth' => []]],
        );

        $openApi->getPaths()->addPath('/api/imports', new PathItem(post: $uploadOperation));

        return $openApi->withComponents($this->withBearerSecurityScheme($openApi->getComponents()));
    }

    private function withBearerSecurityScheme(Components $components): Components
    {
        $securitySchemes = $components->getSecuritySchemes() ?? new ArrayObject();
        $securitySchemes['bearerAuth'] = new SecurityScheme(
            type: 'http',
            description: 'JWT Bearer token',
            scheme: 'bearer',
            bearerFormat: 'JWT',
        );

        return $components->withSecuritySchemes($securitySchemes);
    }
}
