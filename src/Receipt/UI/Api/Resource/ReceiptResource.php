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

namespace App\Receipt\UI\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Receipt\UI\Api\Resource\Input\ReceiptInput;
use App\Receipt\UI\Api\Resource\Output\ReceiptOutput;
use App\Receipt\UI\Api\State\ReceiptDeleteStateProcessor;
use App\Receipt\UI\Api\State\ReceiptStateProcessor;
use App\Receipt\UI\Api\State\ReceiptStateProvider;

#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/receipts', output: ReceiptOutput::class, provider: ReceiptStateProvider::class),
        new Get(
            uriTemplate: '/receipts/{id}',
            output: ReceiptOutput::class,
            provider: ReceiptStateProvider::class,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
        new Post(uriTemplate: '/receipts', input: ReceiptInput::class, output: ReceiptOutput::class, processor: ReceiptStateProcessor::class),
        new Delete(
            uriTemplate: '/receipts/{id}',
            processor: ReceiptDeleteStateProcessor::class,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
    ],
)]
final class ReceiptResource
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    #[ApiProperty(identifier: true)]
    public ?string $id = null;
}
