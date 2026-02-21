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
#[ORM\Table(name: 'maintenance_planned_costs')]
#[ORM\Index(name: 'idx_maintenance_planned_owner_date', columns: ['owner_id', 'planned_for'])]
#[ORM\Index(name: 'idx_maintenance_planned_vehicle_date', columns: ['vehicle_id', 'planned_for'])]
class MaintenancePlannedCostEntity
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

    #[ORM\Column(type: 'string', length: 160)]
    private string $label;

    #[ORM\Column(type: 'string', enumType: MaintenanceEventType::class, length: 32, nullable: true)]
    private ?MaintenanceEventType $eventType = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'planned_for')]
    private DateTimeImmutable $plannedFor;

    #[ORM\Column(type: 'integer', name: 'planned_cost_cents')]
    private int $plannedCostCents;

    #[ORM\Column(type: 'string', length: 3, name: 'currency_code')]
    private string $currencyCode;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getEventType(): ?MaintenanceEventType
    {
        return $this->eventType;
    }

    public function setEventType(?MaintenanceEventType $eventType): void
    {
        $this->eventType = $eventType;
    }

    public function getPlannedFor(): DateTimeImmutable
    {
        return $this->plannedFor;
    }

    public function setPlannedFor(DateTimeImmutable $plannedFor): void
    {
        $this->plannedFor = $plannedFor;
    }

    public function getPlannedCostCents(): int
    {
        return $this->plannedCostCents;
    }

    public function setPlannedCostCents(int $plannedCostCents): void
    {
        $this->plannedCostCents = $plannedCostCents;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
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
