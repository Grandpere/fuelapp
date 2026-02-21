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

use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'receipts')]
class ReceiptEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $issuedAt;

    #[ORM\Column(type: 'integer')]
    private int $totalCents;

    #[ORM\Column(type: 'integer')]
    private int $vatAmountCents = 0;

    #[ORM\ManyToOne(targetEntity: StationEntity::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?StationEntity $station = null;

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?UserEntity $owner = null;

    #[ORM\ManyToOne(targetEntity: VehicleEntity::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?VehicleEntity $vehicle = null;

    /** @var Collection<int, ReceiptLineEntity> */
    #[ORM\OneToMany(mappedBy: 'receipt', targetEntity: ReceiptLineEntity::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getIssuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(DateTimeImmutable $issuedAt): void
    {
        $this->issuedAt = $issuedAt;
    }

    public function getTotalCents(): int
    {
        return $this->totalCents;
    }

    public function setTotalCents(int $totalCents): void
    {
        $this->totalCents = $totalCents;
    }

    public function getVatAmountCents(): int
    {
        return $this->vatAmountCents;
    }

    public function setVatAmountCents(int $vatAmountCents): void
    {
        $this->vatAmountCents = $vatAmountCents;
    }

    public function getStation(): ?StationEntity
    {
        return $this->station;
    }

    public function setStation(?StationEntity $station): void
    {
        $this->station = $station;
    }

    public function getOwner(): ?UserEntity
    {
        return $this->owner;
    }

    public function setOwner(?UserEntity $owner): void
    {
        $this->owner = $owner;
    }

    public function getVehicle(): ?VehicleEntity
    {
        return $this->vehicle;
    }

    public function setVehicle(?VehicleEntity $vehicle): void
    {
        $this->vehicle = $vehicle;
    }

    /** @return Collection<int, ReceiptLineEntity> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(ReceiptLineEntity $line): void
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setReceipt($this);
        }
    }

    public function clearLines(): void
    {
        $this->lines->clear();
    }
}
