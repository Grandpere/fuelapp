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

use App\Maintenance\Domain\MaintenanceReminder;

interface MaintenanceReminderRepository
{
    public function saveIfNew(MaintenanceReminder $reminder): bool;

    /** @return iterable<MaintenanceReminder> */
    public function allForOwner(string $ownerId): iterable;
}
