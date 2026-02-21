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

use App\Maintenance\Domain\MaintenanceEvent;
use DateTimeImmutable;

interface MaintenanceEventRepository
{
    public function save(MaintenanceEvent $event): void;

    public function get(string $id): ?MaintenanceEvent;

    public function delete(string $id): void;

    /** @return iterable<MaintenanceEvent> */
    public function allForOwner(string $ownerId): iterable;

    /** @return iterable<MaintenanceEvent> */
    public function allForOwnerAndVehicle(string $ownerId, string $vehicleId): iterable;

    /** @return iterable<MaintenanceEvent> */
    public function allForSystem(): iterable;

    public function sumActualCostsForOwner(?string $vehicleId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, string $ownerId): int;
}
