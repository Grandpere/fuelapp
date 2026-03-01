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
use App\Admin\UI\Api\Resource\Input\AdminIdentityRelinkInput;
use App\Admin\UI\Api\Resource\Output\AdminIdentityOutput;
use App\Admin\UI\Api\State\AdminIdentityDeleteProcessor;
use App\Admin\UI\Api\State\AdminIdentityStateProcessor;
use App\Admin\UI\Api\State\AdminIdentityStateProvider;

#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/admin/identities', output: AdminIdentityOutput::class, provider: AdminIdentityStateProvider::class),
        new Get(
            uriTemplate: '/admin/identities/{id}',
            output: AdminIdentityOutput::class,
            provider: AdminIdentityStateProvider::class,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
        new Patch(
            uriTemplate: '/admin/identities/{id}',
            input: AdminIdentityRelinkInput::class,
            output: AdminIdentityOutput::class,
            processor: AdminIdentityStateProcessor::class,
            read: false,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
        new Delete(
            uriTemplate: '/admin/identities/{id}',
            processor: AdminIdentityDeleteProcessor::class,
            read: false,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
    ],
)]
final class AdminIdentityResource
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    #[ApiProperty(identifier: true)]
    public ?string $id = null;
}
