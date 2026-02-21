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

namespace App\Admin\Application\Audit;

use DateTimeImmutable;

interface AdminAuditLogReader
{
    public function get(string $id): ?AdminAuditLogEntry;

    /** @return iterable<AdminAuditLogEntry> */
    public function search(
        ?string $action,
        ?string $actorId,
        ?string $targetType,
        ?string $targetId,
        ?string $correlationId,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
    ): iterable;
}
