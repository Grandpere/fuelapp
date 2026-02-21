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

use DateTimeImmutable;

final readonly class AdminAuditLogOutput
{
    /**
     * @param array<string, mixed> $diffSummary
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public ?string $actorId,
        public ?string $actorEmail,
        public string $action,
        public string $targetType,
        public string $targetId,
        public array $diffSummary,
        public array $metadata,
        public string $correlationId,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
