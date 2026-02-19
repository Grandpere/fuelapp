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

namespace App\Receipt\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'receipt_lines')]
class ReceiptLineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: ReceiptEntity::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ReceiptEntity $receipt;

    #[ORM\Column(type: 'string', length: 32)]
    private string $fuelType;

    #[ORM\Column(type: 'integer')]
    private int $quantityMilliLiters;

    #[ORM\Column(type: 'integer')]
    private int $unitPriceDeciCentsPerLiter;

    #[ORM\Column(type: 'integer')]
    private int $vatRatePercent;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getReceipt(): ReceiptEntity
    {
        return $this->receipt;
    }

    public function setReceipt(ReceiptEntity $receipt): void
    {
        $this->receipt = $receipt;
    }

    public function getFuelType(): string
    {
        return $this->fuelType;
    }

    public function setFuelType(string $fuelType): void
    {
        $this->fuelType = $fuelType;
    }

    public function getQuantityMilliLiters(): int
    {
        return $this->quantityMilliLiters;
    }

    public function setQuantityMilliLiters(int $quantityMilliLiters): void
    {
        $this->quantityMilliLiters = $quantityMilliLiters;
    }

    public function getUnitPriceDeciCentsPerLiter(): int
    {
        return $this->unitPriceDeciCentsPerLiter;
    }

    public function setUnitPriceDeciCentsPerLiter(int $unitPriceDeciCentsPerLiter): void
    {
        $this->unitPriceDeciCentsPerLiter = $unitPriceDeciCentsPerLiter;
    }

    public function getVatRatePercent(): int
    {
        return $this->vatRatePercent;
    }

    public function setVatRatePercent(int $vatRatePercent): void
    {
        $this->vatRatePercent = $vatRatePercent;
    }
}
