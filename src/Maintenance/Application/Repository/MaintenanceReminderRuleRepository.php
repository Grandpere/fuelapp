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

namespace App\Maintenance\Application\Repository;

use App\Maintenance\Domain\MaintenanceReminderRule;

interface MaintenanceReminderRuleRepository
{
    public function save(MaintenanceReminderRule $rule): void;

    public function get(string $id): ?MaintenanceReminderRule;

    public function delete(string $id): void;

    /** @return iterable<MaintenanceReminderRule> */
    public function allForOwnerAndVehicle(string $ownerId, string $vehicleId): iterable;
}
