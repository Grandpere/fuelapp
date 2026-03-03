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

namespace App\Admin\Application\Repository;

use App\Admin\Application\Identity\AdminIdentityRecord;

interface AdminIdentityRepository
{
    /** @return list<AdminIdentityRecord> */
    public function list(?string $query = null, ?string $provider = null, ?string $userId = null): array;

    public function get(string $id): ?AdminIdentityRecord;

    public function relink(string $id, string $targetUserId): ?AdminIdentityRecord;

    public function unlink(string $id): bool;

    public function userExists(string $id): bool;
}
