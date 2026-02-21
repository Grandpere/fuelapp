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

namespace App\Analytics\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'analytics_daily_fuel_kpis')]
#[ORM\UniqueConstraint(name: 'uniq_analytics_daily_fuel_kpi_dim', columns: ['owner_id', 'day', 'vehicle_id', 'station_id', 'fuel_type'])]
#[ORM\Index(name: 'idx_analytics_daily_owner_day', columns: ['owner_id', 'day'])]
#[ORM\Index(name: 'idx_analytics_daily_owner_vehicle_day', columns: ['owner_id', 'vehicle_id', 'day'])]
#[ORM\Index(name: 'idx_analytics_daily_owner_station_day', columns: ['owner_id', 'station_id', 'day'])]
class AnalyticsDailyFuelKpiEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private int $id;

    #[ORM\Column(type: UuidType::NAME, name: 'owner_id')]
    private Uuid $ownerId;

    #[ORM\Column(type: 'date_immutable')]
    private DateTimeImmutable $day;

    #[ORM\Column(type: UuidType::NAME, nullable: true, name: 'vehicle_id')]
    private ?Uuid $vehicleId = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true, name: 'station_id')]
    private ?Uuid $stationId = null;

    #[ORM\Column(type: 'string', length: 32, name: 'fuel_type')]
    private string $fuelType;

    #[ORM\Column(type: 'integer', name: 'receipt_count')]
    private int $receiptCount;

    #[ORM\Column(type: 'integer', name: 'line_count')]
    private int $lineCount;

    #[ORM\Column(type: 'bigint', name: 'total_cost_cents')]
    private int $totalCostCents;

    #[ORM\Column(type: 'bigint', name: 'total_quantity_milli_liters')]
    private int $totalQuantityMilliLiters;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private DateTimeImmutable $updatedAt;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getOwnerId(): Uuid
    {
        return $this->ownerId;
    }

    public function setOwnerId(Uuid $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    public function getDay(): DateTimeImmutable
    {
        return $this->day;
    }

    public function setDay(DateTimeImmutable $day): void
    {
        $this->day = $day;
    }

    public function getVehicleId(): ?Uuid
    {
        return $this->vehicleId;
    }

    public function setVehicleId(?Uuid $vehicleId): void
    {
        $this->vehicleId = $vehicleId;
    }

    public function getStationId(): ?Uuid
    {
        return $this->stationId;
    }

    public function setStationId(?Uuid $stationId): void
    {
        $this->stationId = $stationId;
    }

    public function getFuelType(): string
    {
        return $this->fuelType;
    }

    public function setFuelType(string $fuelType): void
    {
        $this->fuelType = $fuelType;
    }

    public function getReceiptCount(): int
    {
        return $this->receiptCount;
    }

    public function setReceiptCount(int $receiptCount): void
    {
        $this->receiptCount = $receiptCount;
    }

    public function getLineCount(): int
    {
        return $this->lineCount;
    }

    public function setLineCount(int $lineCount): void
    {
        $this->lineCount = $lineCount;
    }

    public function getTotalCostCents(): int
    {
        return $this->totalCostCents;
    }

    public function setTotalCostCents(int $totalCostCents): void
    {
        $this->totalCostCents = $totalCostCents;
    }

    public function getTotalQuantityMilliLiters(): int
    {
        return $this->totalQuantityMilliLiters;
    }

    public function setTotalQuantityMilliLiters(int $totalQuantityMilliLiters): void
    {
        $this->totalQuantityMilliLiters = $totalQuantityMilliLiters;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
