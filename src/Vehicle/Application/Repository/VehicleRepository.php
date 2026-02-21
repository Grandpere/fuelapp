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

namespace App\Vehicle\Application\Repository;

use App\Vehicle\Domain\Vehicle;

interface VehicleRepository
{
    public function save(Vehicle $vehicle): void;

    public function get(string $id): ?Vehicle;

    public function delete(string $id): void;

    public function findByPlateNumber(string $plateNumber): ?Vehicle;

    /** @return iterable<Vehicle> */
    public function all(): iterable;
}
