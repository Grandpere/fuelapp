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

namespace App\Maintenance\UI\Api\Resource\Output;

use DateTimeImmutable;

final readonly class MaintenanceEventOutput
{
    public function __construct(
        public string $id,
        public string $ownerId,
        public string $vehicleId,
        public string $eventType,
        public DateTimeImmutable $occurredAt,
        public ?string $description,
        public ?int $odometerKilometers,
        public ?int $totalCostCents,
        public string $currencyCode,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
