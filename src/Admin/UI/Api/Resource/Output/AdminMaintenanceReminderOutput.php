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

namespace App\Admin\UI\Api\Resource\Output;

use DateTimeImmutable;

final readonly class AdminMaintenanceReminderOutput
{
    public function __construct(
        public string $id,
        public string $ownerId,
        public string $vehicleId,
        public string $ruleId,
        public ?DateTimeImmutable $dueAtDate,
        public ?int $dueAtOdometerKilometers,
        public bool $dueByDate,
        public bool $dueByOdometer,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
