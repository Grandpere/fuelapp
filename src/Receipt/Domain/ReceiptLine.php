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

namespace App\Receipt\Domain;

use App\Receipt\Domain\Enum\FuelType;
use InvalidArgumentException;

final class ReceiptLine
{
    private FuelType $fuelType;
    private int $quantityMilliLiters;
    private int $unitPriceDeciCentsPerLiter;
    private int $vatRatePercent;

    private function __construct(FuelType $fuelType, int $quantityMilliLiters, int $unitPriceDeciCentsPerLiter, int $vatRatePercent)
    {
        if ($quantityMilliLiters <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }
        if ($unitPriceDeciCentsPerLiter < 0) {
            throw new InvalidArgumentException('Unit price cannot be negative');
        }
        if ($vatRatePercent < 0 || $vatRatePercent > 100) {
            throw new InvalidArgumentException('VAT rate must be between 0 and 100');
        }

        $this->fuelType = $fuelType;
        $this->quantityMilliLiters = $quantityMilliLiters;
        $this->unitPriceDeciCentsPerLiter = $unitPriceDeciCentsPerLiter;
        $this->vatRatePercent = $vatRatePercent;
    }

    public static function create(FuelType $fuelType, int $quantityMilliLiters, int $unitPriceDeciCentsPerLiter, int $vatRatePercent): self
    {
        return new self($fuelType, $quantityMilliLiters, $unitPriceDeciCentsPerLiter, $vatRatePercent);
    }

    public static function reconstitute(FuelType $fuelType, int $quantityMilliLiters, int $unitPriceDeciCentsPerLiter, int $vatRatePercent): self
    {
        return new self($fuelType, $quantityMilliLiters, $unitPriceDeciCentsPerLiter, $vatRatePercent);
    }

    public function fuelType(): FuelType
    {
        return $this->fuelType;
    }

    public function quantityMilliLiters(): int
    {
        return $this->quantityMilliLiters;
    }

    public function unitPriceDeciCentsPerLiter(): int
    {
        return $this->unitPriceDeciCentsPerLiter;
    }

    public function vatRatePercent(): int
    {
        return $this->vatRatePercent;
    }

    public function lineTotalCents(): int
    {
        // deci-cents per liter * milliliters / 10000 => cents
        return (int) round(($this->unitPriceDeciCentsPerLiter * $this->quantityMilliLiters) / 10000, 0, PHP_ROUND_HALF_UP);
    }

    public function vatAmountCents(): int
    {
        if (0 === $this->vatRatePercent) {
            return 0;
        }

        // Fuel prices are TTC on receipts, so VAT is the included part:
        // VAT = TTC * rate / (100 + rate)
        return (int) round(
            $this->lineTotalCents() * $this->vatRatePercent / (100 + $this->vatRatePercent),
            0,
            PHP_ROUND_HALF_UP,
        );
    }
}
