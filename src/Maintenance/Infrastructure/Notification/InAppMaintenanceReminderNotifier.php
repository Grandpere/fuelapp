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

namespace App\Maintenance\Infrastructure\Notification;

use App\Maintenance\Application\Notification\MaintenanceReminderNotifier;
use App\Maintenance\Domain\MaintenanceReminder;
use Psr\Log\LoggerInterface;

final readonly class InAppMaintenanceReminderNotifier implements MaintenanceReminderNotifier
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function notifyCreated(MaintenanceReminder $reminder): void
    {
        $this->logger->info('maintenance.reminder.created', [
            'reminder_id' => $reminder->id()->toString(),
            'owner_id' => $reminder->ownerId(),
            'vehicle_id' => $reminder->vehicleId(),
            'rule_id' => $reminder->ruleId(),
            'due_at_date' => $reminder->dueAtDate()?->format(DATE_ATOM),
            'due_at_odometer_km' => $reminder->dueAtOdometerKilometers(),
            'due_by_date' => $reminder->dueByDate(),
            'due_by_odometer' => $reminder->dueByOdometer(),
        ]);
    }
}
