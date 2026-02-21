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

namespace App\Maintenance\Application\Reminder;

use App\Maintenance\Domain\Enum\ReminderRuleTriggerMode;
use DateTimeImmutable;

final readonly class ReminderDueState
{
    public function __construct(
        public string $ruleId,
        public string $ruleName,
        public ReminderRuleTriggerMode $triggerMode,
        public ?DateTimeImmutable $dueAtDate,
        public ?int $dueAtOdometerKilometers,
        public bool $dueByDate,
        public bool $dueByOdometer,
        public bool $isDue,
        public ?DateTimeImmutable $lastEventOccurredAt,
        public ?int $lastEventOdometerKilometers,
    ) {
    }
}
