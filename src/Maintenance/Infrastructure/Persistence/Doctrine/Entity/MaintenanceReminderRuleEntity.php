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
use App\Maintenance\Domain\Enum\ReminderRuleTriggerMode;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'maintenance_reminder_rules')]
#[ORM\Index(name: 'idx_maintenance_reminder_owner_vehicle', columns: ['owner_id', 'vehicle_id'])]
class MaintenanceReminderRuleEntity
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

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(type: 'string', enumType: ReminderRuleTriggerMode::class, length: 24)]
    private ReminderRuleTriggerMode $triggerMode;

    #[ORM\Column(type: 'string', enumType: MaintenanceEventType::class, length: 32, nullable: true)]
    private ?MaintenanceEventType $eventType = null;

    #[ORM\Column(type: 'integer', nullable: true, name: 'interval_days')]
    private ?int $intervalDays = null;

    #[ORM\Column(type: 'integer', nullable: true, name: 'interval_kilometers')]
    private ?int $intervalKilometers = null;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTriggerMode(): ReminderRuleTriggerMode
    {
        return $this->triggerMode;
    }

    public function setTriggerMode(ReminderRuleTriggerMode $triggerMode): void
    {
        $this->triggerMode = $triggerMode;
    }

    public function getEventType(): ?MaintenanceEventType
    {
        return $this->eventType;
    }

    public function setEventType(?MaintenanceEventType $eventType): void
    {
        $this->eventType = $eventType;
    }

    public function getIntervalDays(): ?int
    {
        return $this->intervalDays;
    }

    public function setIntervalDays(?int $intervalDays): void
    {
        $this->intervalDays = $intervalDays;
    }

    public function getIntervalKilometers(): ?int
    {
        return $this->intervalKilometers;
    }

    public function setIntervalKilometers(?int $intervalKilometers): void
    {
        $this->intervalKilometers = $intervalKilometers;
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
