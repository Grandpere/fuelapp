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

namespace App\PublicFuelStation\Application\Repository;

interface PublicFuelStationSyncRunRepository
{
    public function start(string $sourceUrl): string;

    public function finish(string $id, string $status, int $processedCount, int $upsertedCount, int $rejectedCount, ?string $errorMessage = null): void;
}
