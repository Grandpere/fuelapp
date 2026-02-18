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
    private int $unitPriceCentsPerLiter;
    private int $vatRatePercent;

    private function __construct(FuelType $fuelType, int $quantityMilliLiters, int $unitPriceCentsPerLiter, int $vatRatePercent)
    {
        if ($quantityMilliLiters <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }
        if ($unitPriceCentsPerLiter < 0) {
            throw new InvalidArgumentException('Unit price cannot be negative');
        }
        if ($vatRatePercent < 0 || $vatRatePercent > 100) {
            throw new InvalidArgumentException('VAT rate must be between 0 and 100');
        }

        $this->fuelType = $fuelType;
        $this->quantityMilliLiters = $quantityMilliLiters;
        $this->unitPriceCentsPerLiter = $unitPriceCentsPerLiter;
        $this->vatRatePercent = $vatRatePercent;
    }

    public static function create(FuelType $fuelType, int $quantityMilliLiters, int $unitPriceCentsPerLiter, int $vatRatePercent): self
    {
        return new self($fuelType, $quantityMilliLiters, $unitPriceCentsPerLiter, $vatRatePercent);
    }

    public static function reconstitute(FuelType $fuelType, int $quantityMilliLiters, int $unitPriceCentsPerLiter, int $vatRatePercent): self
    {
        return new self($fuelType, $quantityMilliLiters, $unitPriceCentsPerLiter, $vatRatePercent);
    }

    public function fuelType(): FuelType
    {
        return $this->fuelType;
    }

    public function quantityMilliLiters(): int
    {
        return $this->quantityMilliLiters;
    }

    public function unitPriceCentsPerLiter(): int
    {
        return $this->unitPriceCentsPerLiter;
    }

    public function vatRatePercent(): int
    {
        return $this->vatRatePercent;
    }

    public function lineTotalCents(): int
    {
        // cents per liter * milliliters / 1000
        return (int) round(($this->unitPriceCentsPerLiter * $this->quantityMilliLiters) / 1000, 0, PHP_ROUND_HALF_UP);
    }

    public function vatAmountCents(): int
    {
        return (int) round($this->lineTotalCents() * $this->vatRatePercent / 100, 0, PHP_ROUND_HALF_UP);
    }
}
