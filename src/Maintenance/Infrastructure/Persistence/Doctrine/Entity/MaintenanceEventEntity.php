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

namespace App\Maintenance\Infrastructure\Persistence\Doctrine\Entity;

use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'maintenance_events')]
#[ORM\Index(name: 'idx_maintenance_owner_occurred_at', columns: ['owner_id', 'occurred_at'])]
#[ORM\Index(name: 'idx_maintenance_vehicle_occurred_at', columns: ['vehicle_id', 'occurred_at'])]
class MaintenanceEventEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
    private UserEntity $owner;

    #[ORM\ManyToOne(targetEntity: VehicleEntity::class)]
    #[ORM\JoinColumn(name: 'vehicle_id', nullable: false, onDelete: 'CASCADE')]
    private VehicleEntity $vehicle;

    #[ORM\Column(type: 'string', enumType: MaintenanceEventType::class, length: 32)]
    private MaintenanceEventType $eventType;

    #[ORM\Column(type: 'datetime_immutable', name: 'occurred_at')]
    private DateTimeImmutable $occurredAt;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', nullable: true, name: 'odometer_kilometers')]
    private ?int $odometerKilometers = null;

    #[ORM\Column(type: 'integer', nullable: true, name: 'total_cost_cents')]
    private ?int $totalCostCents = null;

    #[ORM\Column(type: 'string', length: 3, name: 'currency_code')]
    private string $currencyCode;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private DateTimeImmutable $updatedAt;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getOwner(): UserEntity
    {
        return $this->owner;
    }

    public function setOwner(UserEntity $owner): void
    {
        $this->owner = $owner;
    }

    public function getVehicle(): VehicleEntity
    {
        return $this->vehicle;
    }

    public function setVehicle(VehicleEntity $vehicle): void
    {
        $this->vehicle = $vehicle;
    }

    public function getEventType(): MaintenanceEventType
    {
        return $this->eventType;
    }

    public function setEventType(MaintenanceEventType $eventType): void
    {
        $this->eventType = $eventType;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(DateTimeImmutable $occurredAt): void
    {
        $this->occurredAt = $occurredAt;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getOdometerKilometers(): ?int
    {
        return $this->odometerKilometers;
    }

    public function setOdometerKilometers(?int $odometerKilometers): void
    {
        $this->odometerKilometers = $odometerKilometers;
    }

    public function getTotalCostCents(): ?int
    {
        return $this->totalCostCents;
    }

    public function setTotalCostCents(?int $totalCostCents): void
    {
        $this->totalCostCents = $totalCostCents;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
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
