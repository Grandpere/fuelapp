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
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Receipt\UI\Api\Resource\Input\ReceiptInput;
use App\Receipt\UI\Api\Resource\Output\ReceiptOutput;
use App\Receipt\UI\Api\State\ReceiptStateProcessor;
use App\Receipt\UI\Api\State\ReceiptStateProvider;

#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/receipts', output: ReceiptOutput::class, provider: ReceiptStateProvider::class),
        new Get(uriTemplate: '/receipts/{id}', output: ReceiptOutput::class, provider: ReceiptStateProvider::class),
        new Post(uriTemplate: '/receipts', input: ReceiptInput::class, output: ReceiptOutput::class, processor: ReceiptStateProcessor::class),
    ],
)]
final class ReceiptResource
{
    #[ApiProperty(identifier: true)]
    public ?string $id = null;
}
