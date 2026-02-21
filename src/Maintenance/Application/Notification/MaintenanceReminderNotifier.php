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

namespace App\Maintenance\Application\Notification;

use App\Maintenance\Domain\MaintenanceReminder;

interface MaintenanceReminderNotifier
{
    public function notifyCreated(MaintenanceReminder $reminder): void;
}
