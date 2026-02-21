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

namespace App\Import\Application\Parsing;

final readonly class ParsedReceiptLineDraft
{
    public function __construct(
        public ?string $fuelType,
        public ?int $quantityMilliLiters,
        public ?int $unitPriceDeciCentsPerLiter,
        public ?int $lineTotalCents,
        public ?int $vatRatePercent,
    ) {
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'fuelType' => $this->fuelType,
            'quantityMilliLiters' => $this->quantityMilliLiters,
            'unitPriceDeciCentsPerLiter' => $this->unitPriceDeciCentsPerLiter,
            'lineTotalCents' => $this->lineTotalCents,
            'vatRatePercent' => $this->vatRatePercent,
        ];
    }
}
