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

use App\Maintenance\Domain\MaintenancePlannedCost;
use DateTimeImmutable;

interface MaintenancePlannedCostRepository
{
    public function save(MaintenancePlannedCost $item): void;

    public function get(string $id): ?MaintenancePlannedCost;

    public function delete(string $id): void;

    /** @return iterable<MaintenancePlannedCost> */
    public function allForOwner(string $ownerId): iterable;

    public function sumPlannedCostsForOwner(?string $vehicleId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, string $ownerId): int;
}
