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

final class ReceiptLineOutput
{
    public function __construct(
        public string $fuelType,
        public int $quantityMilliLiters,
        public int $unitPriceDeciCentsPerLiter,
        public int $lineTotalCents,
        public int $vatRatePercent,
        public int $vatAmountCents,
    ) {
    }
}
