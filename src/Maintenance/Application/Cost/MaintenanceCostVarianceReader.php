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

namespace App\Maintenance\Application\Cost;

use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Application\Repository\MaintenancePlannedCostRepository;
use DateTimeImmutable;

final readonly class MaintenanceCostVarianceReader
{
    public function __construct(
        private MaintenancePlannedCostRepository $plannedCostRepository,
        private MaintenanceEventRepository $eventRepository,
    ) {
    }

    public function read(string $ownerId, ?string $vehicleId, ?DateTimeImmutable $from, ?DateTimeImmutable $to): MaintenanceCostVariance
    {
        $planned = $this->plannedCostRepository->sumPlannedCostsForOwner($vehicleId, $from, $to, $ownerId);
        $actual = $this->eventRepository->sumActualCostsForOwner($vehicleId, $from, $to, $ownerId);

        return new MaintenanceCostVariance(
            $vehicleId,
            $planned,
            $actual,
            $actual - $planned,
        );
    }
}
