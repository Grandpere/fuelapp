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

namespace App\Maintenance\Infrastructure\Reminder;

use App\Maintenance\Application\Reminder\VehicleCurrentOdometerResolver;
use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Receipt\Application\Repository\ReceiptRepository;

final readonly class EventAndReceiptCurrentOdometerResolver implements VehicleCurrentOdometerResolver
{
    public function __construct(
        private MaintenanceEventRepository $eventRepository,
        private ReceiptRepository $receiptRepository,
    ) {
    }

    public function resolve(string $ownerId, string $vehicleId): ?int
    {
        $eventOdometer = $this->maxEventOdometer($ownerId, $vehicleId);
        $receiptOdometer = $this->receiptRepository->maxOdometerKilometersForOwnerAndVehicle($ownerId, $vehicleId);

        if (null === $eventOdometer) {
            return $receiptOdometer;
        }

        if (null === $receiptOdometer) {
            return $eventOdometer;
        }

        return max($eventOdometer, $receiptOdometer);
    }

    private function maxEventOdometer(string $ownerId, string $vehicleId): ?int
    {
        $max = null;
        foreach ($this->eventRepository->allForOwnerAndVehicle($ownerId, $vehicleId) as $event) {
            $odometer = $event->odometerKilometers();
            if (null === $odometer) {
                continue;
            }

            $max = null === $max ? $odometer : max($max, $odometer);
        }

        return $max;
    }
}
