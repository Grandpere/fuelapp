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

namespace App\Maintenance\Domain;

use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\ValueObject\MaintenanceEventId;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class MaintenanceEvent
{
    private MaintenanceEventId $id;
    private string $ownerId;
    private string $vehicleId;
    private MaintenanceEventType $eventType;
    private DateTimeImmutable $occurredAt;
    private ?string $description;
    private ?int $odometerKilometers;
    private ?int $totalCostCents;
    private string $currencyCode;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        MaintenanceEventId $id,
        string $ownerId,
        string $vehicleId,
        MaintenanceEventType $eventType,
        DateTimeImmutable $occurredAt,
        ?string $description,
        ?int $odometerKilometers,
        ?int $totalCostCents,
        string $currencyCode,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->ownerId = self::normalizeUuid($ownerId, 'ownerId');
        $this->vehicleId = self::normalizeUuid($vehicleId, 'vehicleId');
        $this->eventType = $eventType;
        $this->occurredAt = $occurredAt;
        $this->description = self::normalizeDescription($description);
        $this->odometerKilometers = self::normalizeNullableNonNegativeInt($odometerKilometers, 'odometerKilometers');
        $this->totalCostCents = self::normalizeNullableNonNegativeInt($totalCostCents, 'totalCostCents');
        $this->currencyCode = self::normalizeCurrencyCode($currencyCode);
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(
        string $ownerId,
        string $vehicleId,
        MaintenanceEventType $eventType,
        DateTimeImmutable $occurredAt,
        ?string $description = null,
        ?int $odometerKilometers = null,
        ?int $totalCostCents = null,
        string $currencyCode = 'EUR',
        ?DateTimeImmutable $now = null,
    ): self {
        $now ??= new DateTimeImmutable();

        return new self(
            MaintenanceEventId::new(),
            $ownerId,
            $vehicleId,
            $eventType,
            $occurredAt,
            $description,
            $odometerKilometers,
            $totalCostCents,
            $currencyCode,
            $now,
            $now,
        );
    }

    public static function reconstitute(
        MaintenanceEventId $id,
        string $ownerId,
        string $vehicleId,
        MaintenanceEventType $eventType,
        DateTimeImmutable $occurredAt,
        ?string $description,
        ?int $odometerKilometers,
        ?int $totalCostCents,
        string $currencyCode,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            $ownerId,
            $vehicleId,
            $eventType,
            $occurredAt,
            $description,
            $odometerKilometers,
            $totalCostCents,
            $currencyCode,
            $createdAt,
            $updatedAt,
        );
    }

    public function id(): MaintenanceEventId
    {
        return $this->id;
    }

    public function ownerId(): string
    {
        return $this->ownerId;
    }

    public function vehicleId(): string
    {
        return $this->vehicleId;
    }

    public function eventType(): MaintenanceEventType
    {
        return $this->eventType;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function odometerKilometers(): ?int
    {
        return $this->odometerKilometers;
    }

    public function totalCostCents(): ?int
    {
        return $this->totalCostCents;
    }

    public function currencyCode(): string
    {
        return $this->currencyCode;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(
        string $vehicleId,
        MaintenanceEventType $eventType,
        DateTimeImmutable $occurredAt,
        ?string $description,
        ?int $odometerKilometers,
        ?int $totalCostCents,
        string $currencyCode,
        ?DateTimeImmutable $at = null,
    ): void {
        $at ??= new DateTimeImmutable();

        $this->vehicleId = self::normalizeUuid($vehicleId, 'vehicleId');
        $this->eventType = $eventType;
        $this->occurredAt = $occurredAt;
        $this->description = self::normalizeDescription($description);
        $this->odometerKilometers = self::normalizeNullableNonNegativeInt($odometerKilometers, 'odometerKilometers');
        $this->totalCostCents = self::normalizeNullableNonNegativeInt($totalCostCents, 'totalCostCents');
        $this->currencyCode = self::normalizeCurrencyCode($currencyCode);
        $this->updatedAt = $at;
    }

    private static function normalizeUuid(string $value, string $field): string
    {
        $normalized = trim($value);
        if ('' === $normalized || !Uuid::isValid($normalized)) {
            throw new InvalidArgumentException(sprintf('%s must be a valid UUID.', $field));
        }

        return $normalized;
    }

    private static function normalizeDescription(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $normalized = trim($value);

        return '' === $normalized ? null : mb_substr($normalized, 0, 2000);
    }

    private static function normalizeNullableNonNegativeInt(?int $value, string $field): ?int
    {
        if (null === $value) {
            return null;
        }

        if ($value < 0) {
            throw new InvalidArgumentException(sprintf('%s must be a non-negative integer.', $field));
        }

        return $value;
    }

    private static function normalizeCurrencyCode(string $value): string
    {
        $normalized = mb_strtoupper(trim($value));
        if (3 !== mb_strlen($normalized)) {
            throw new InvalidArgumentException('currencyCode must be an ISO-4217 3-letter code.');
        }

        return $normalized;
    }
}
