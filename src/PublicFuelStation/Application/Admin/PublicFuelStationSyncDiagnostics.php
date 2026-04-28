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

final readonly class PublicFuelStationSyncDiagnostics
{
    /** @param list<PublicFuelStationSyncRunSummary> $recentRuns */
    public function __construct(
        public int $stationCount,
        public ?DateTimeImmutable $latestSourceUpdatedAt,
        public ?DateTimeImmutable $latestImportedAt,
        public ?PublicFuelStationSyncRunSummary $latestRun,
        public array $recentRuns,
    ) {
    }
}
