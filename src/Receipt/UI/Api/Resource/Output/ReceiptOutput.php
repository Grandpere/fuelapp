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

namespace App\Receipt\UI\Api\Resource\Output;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final class ReceiptOutput
{
    /** @param list<ReceiptLineOutput> $lines */
    public function __construct(
        public string $id,
        public DateTimeImmutable $issuedAt,
        public int $totalCents,
        public int $vatAmountCents,
        public Uuid $stationId,
        public array $lines,
    ) {
    }
}
