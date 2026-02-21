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

namespace App\Vehicle\Domain;

use App\Vehicle\Domain\ValueObject\VehicleId;
use DateTimeImmutable;

final class Vehicle
{
    private VehicleId $id;
    private ?string $ownerId;
    private string $name;
    private string $plateNumber;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        VehicleId $id,
        ?string $ownerId,
        string $name,
        string $plateNumber,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->ownerId = $ownerId;
        $this->name = $name;
        $this->plateNumber = $plateNumber;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(string $ownerId, string $name, string $plateNumber, ?DateTimeImmutable $now = null): self
    {
        $now ??= new DateTimeImmutable();

        return new self(
            VehicleId::new(),
            trim($ownerId),
            trim($name),
            self::normalizePlateNumber($plateNumber),
            $now,
            $now,
        );
    }

    public static function reconstitute(
        VehicleId $id,
        ?string $ownerId,
        string $name,
        string $plateNumber,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            null === $ownerId ? null : trim($ownerId),
            trim($name),
            self::normalizePlateNumber($plateNumber),
            $createdAt,
            $updatedAt,
        );
    }

    public function id(): VehicleId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function ownerId(): ?string
    {
        return $this->ownerId;
    }

    public function plateNumber(): string
    {
        return $this->plateNumber;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(string $ownerId, string $name, string $plateNumber, ?DateTimeImmutable $at = null): void
    {
        $at ??= new DateTimeImmutable();

        $this->ownerId = trim($ownerId);
        $this->name = trim($name);
        $this->plateNumber = self::normalizePlateNumber($plateNumber);
        $this->updatedAt = $at;
    }

    private static function normalizePlateNumber(string $plateNumber): string
    {
        return mb_strtoupper(trim($plateNumber));
    }
}
