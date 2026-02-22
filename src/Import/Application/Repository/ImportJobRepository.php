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

namespace App\Import\Application\Repository;

use App\Import\Domain\ImportJob;

interface ImportJobRepository
{
    public function save(ImportJob $job): void;

    public function deleteForSystem(string $id): void;

    public function get(string $id): ?ImportJob;

    public function getForSystem(string $id): ?ImportJob;

    public function findLatestByOwnerAndChecksum(string $ownerId, string $checksumSha256, ?string $excludeJobId = null): ?ImportJob;

    /** @return iterable<ImportJob> */
    public function all(): iterable;

    /** @return iterable<ImportJob> */
    public function allForSystem(): iterable;
}
