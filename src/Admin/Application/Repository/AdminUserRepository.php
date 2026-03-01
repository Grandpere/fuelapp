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

use App\Admin\Application\User\AdminUserRecord;

interface AdminUserRepository
{
    /** @return list<AdminUserRecord> */
    public function list(?string $query = null, ?string $role = null, ?bool $isActive = null): array;

    public function get(string $id): ?AdminUserRecord;

    public function update(string $id, ?bool $isActive, ?bool $isAdmin): ?AdminUserRecord;

    public function countActiveAdmins(): int;
}
