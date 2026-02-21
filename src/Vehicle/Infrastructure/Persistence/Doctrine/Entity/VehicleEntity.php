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

namespace App\Vehicle\Infrastructure\Persistence\Doctrine\Entity;

use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'vehicles')]
#[ORM\UniqueConstraint(name: 'uniq_vehicle_owner_plate_number', columns: ['owner_id', 'plate_number'])]
#[ORM\Index(name: 'idx_vehicle_name', columns: ['name'])]
#[ORM\Index(name: 'idx_vehicle_owner_id', columns: ['owner_id'])]
class VehicleEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(type: 'string', length: 32, name: 'plate_number')]
    private string $plateNumber;

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(name: 'owner_id', nullable: true, onDelete: 'SET NULL')]
    private ?UserEntity $owner = null;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPlateNumber(): string
    {
        return $this->plateNumber;
    }

    public function setPlateNumber(string $plateNumber): void
    {
        $this->plateNumber = $plateNumber;
    }

    public function getOwner(): ?UserEntity
    {
        return $this->owner;
    }

    public function setOwner(?UserEntity $owner): void
    {
        $this->owner = $owner;
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
