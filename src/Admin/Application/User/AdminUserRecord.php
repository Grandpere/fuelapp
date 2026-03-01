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

namespace App\Admin\Application\User;

final readonly class AdminUserRecord
{
    /** @param list<string> $roles */
    public function __construct(
        public string $id,
        public string $email,
        public array $roles,
        public bool $isActive,
        public int $identityCount,
    ) {
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->roles, true);
    }
}
