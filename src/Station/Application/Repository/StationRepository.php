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

namespace App\Station\Application\Repository;

use App\Station\Domain\Station;

interface StationRepository
{
    public function save(Station $station): void;

    public function get(string $id): ?Station;

    public function getForSystem(string $id): ?Station;

    public function deleteForSystem(string $id): void;

    public function delete(string $id): void;

    /** @param list<string> $ids
     * @return array<string, Station>
     */
    public function getByIds(array $ids): array;

    public function findByIdentity(string $name, string $streetName, string $postalCode, string $city): ?Station;

    /** @return iterable<Station> */
    public function all(): iterable;

    /** @return iterable<Station> */
    public function allForSystem(): iterable;
}
