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

namespace App\Admin\UI\Api\Resource\Output;

final readonly class AdminUserOutput
{
    /** @param list<string> $roles */
    public function __construct(
        public string $id,
        public string $email,
        public array $roles,
        public bool $isActive,
        public bool $isAdmin,
        public int $identityCount,
    ) {
    }
}
