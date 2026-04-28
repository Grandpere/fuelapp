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

namespace App\PublicFuelStation\Application\Admin;

use DateTimeImmutable;

final readonly class PublicFuelStationSyncRunSummary
{
    public function __construct(
        public string $id,
        public string $sourceUrl,
        public string $status,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $completedAt,
        public int $processedCount,
        public int $upsertedCount,
        public int $rejectedCount,
        public ?string $errorMessage,
    ) {
    }
}
