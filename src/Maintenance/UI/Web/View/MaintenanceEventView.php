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

namespace App\Maintenance\UI\Web\View;

use App\Maintenance\Domain\MaintenanceEvent;

final readonly class MaintenanceEventView
{
    private function __construct(
        public string $id,
        public string $ownerId,
        public string $vehicleId,
        public string $eventType,
        public string $occurredAt,
        public ?string $description,
        public ?int $odometerKilometers,
        public ?int $totalCostCents,
        public string $currencyCode,
    ) {
    }

    public static function fromDomain(MaintenanceEvent $event): self
    {
        return new self(
            $event->id()->toString(),
            $event->ownerId(),
            $event->vehicleId(),
            $event->eventType()->value,
            $event->occurredAt()->format(DATE_ATOM),
            $event->description(),
            $event->odometerKilometers(),
            $event->totalCostCents(),
            $event->currencyCode(),
        );
    }
}
