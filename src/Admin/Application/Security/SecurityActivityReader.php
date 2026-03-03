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

namespace App\Admin\Application\Security;

use DateTimeImmutable;

interface SecurityActivityReader
{
    public function get(string $id): ?SecurityActivityEntry;

    /** @return iterable<SecurityActivityEntry> */
    public function search(?string $action, ?string $actorId, ?DateTimeImmutable $from, ?DateTimeImmutable $to): iterable;
}
