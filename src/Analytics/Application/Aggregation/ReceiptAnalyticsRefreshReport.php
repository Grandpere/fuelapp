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

namespace App\Analytics\Application\Aggregation;

use DateTimeImmutable;

final readonly class ReceiptAnalyticsRefreshReport
{
    public function __construct(
        public int $rowsMaterialized,
        public int $sourceReceiptCount,
        public ?DateTimeImmutable $sourceMaxIssuedAt,
        public DateTimeImmutable $refreshedAt,
    ) {
    }
}
