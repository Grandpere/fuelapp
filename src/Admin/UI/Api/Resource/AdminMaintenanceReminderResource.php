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
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Admin\UI\Api\Resource\Output\AdminMaintenanceReminderOutput;
use App\Admin\UI\Api\State\AdminMaintenanceReminderStateProvider;

#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/admin/maintenance/reminders', output: AdminMaintenanceReminderOutput::class, provider: AdminMaintenanceReminderStateProvider::class),
        new Get(
            uriTemplate: '/admin/maintenance/reminders/{id}',
            output: AdminMaintenanceReminderOutput::class,
            provider: AdminMaintenanceReminderStateProvider::class,
            requirements: ['id' => self::UUID_ROUTE_REQUIREMENT],
        ),
    ],
)]
final class AdminMaintenanceReminderResource
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    #[ApiProperty(identifier: true)]
    public ?string $id = null;
}
