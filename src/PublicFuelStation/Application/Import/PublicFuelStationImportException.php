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

namespace App\PublicFuelStation\Application\Import;

use RuntimeException;
use Throwable;

final class PublicFuelStationImportException extends RuntimeException
{
    public function __construct(
        public readonly int $processedCount,
        public readonly int $upsertedCount,
        public readonly int $rejectedCount,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
