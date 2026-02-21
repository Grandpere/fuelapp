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

use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'maintenance_reminders')]
#[ORM\UniqueConstraint(name: 'uniq_maintenance_reminder_dedup_key', columns: ['dedup_key'])]
#[ORM\Index(name: 'idx_maintenance_reminders_owner_created', columns: ['owner_id', 'created_at'])]
class MaintenanceReminderEntity
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

    #[ORM\ManyToOne(targetEntity: MaintenanceReminderRuleEntity::class)]
    #[ORM\JoinColumn(name: 'rule_id', nullable: false, onDelete: 'CASCADE')]
    private MaintenanceReminderRuleEntity $rule;

    #[ORM\Column(type: 'string', length: 64, name: 'dedup_key')]
    private string $dedupKey;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'due_at_date')]
    private ?DateTimeImmutable $dueAtDate = null;

    #[ORM\Column(type: 'integer', nullable: true, name: 'due_at_odometer_kilometers')]
    private ?int $dueAtOdometerKilometers = null;

    #[ORM\Column(type: 'boolean', name: 'due_by_date')]
    private bool $dueByDate = false;

    #[ORM\Column(type: 'boolean', name: 'due_by_odometer')]
    private bool $dueByOdometer = false;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private DateTimeImmutable $createdAt;

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

    public function getRule(): MaintenanceReminderRuleEntity
    {
        return $this->rule;
    }

    public function setRule(MaintenanceReminderRuleEntity $rule): void
    {
        $this->rule = $rule;
    }

    public function getDedupKey(): string
    {
        return $this->dedupKey;
    }

    public function setDedupKey(string $dedupKey): void
    {
        $this->dedupKey = $dedupKey;
    }

    public function getDueAtDate(): ?DateTimeImmutable
    {
        return $this->dueAtDate;
    }

    public function setDueAtDate(?DateTimeImmutable $dueAtDate): void
    {
        $this->dueAtDate = $dueAtDate;
    }

    public function getDueAtOdometerKilometers(): ?int
    {
        return $this->dueAtOdometerKilometers;
    }

    public function setDueAtOdometerKilometers(?int $dueAtOdometerKilometers): void
    {
        $this->dueAtOdometerKilometers = $dueAtOdometerKilometers;
    }

    public function isDueByDate(): bool
    {
        return $this->dueByDate;
    }

    public function setDueByDate(bool $dueByDate): void
    {
        $this->dueByDate = $dueByDate;
    }

    public function isDueByOdometer(): bool
    {
        return $this->dueByOdometer;
    }

    public function setDueByOdometer(bool $dueByOdometer): void
    {
        $this->dueByOdometer = $dueByOdometer;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
