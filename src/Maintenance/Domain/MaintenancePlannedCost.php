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
use App\Maintenance\Domain\ValueObject\MaintenancePlannedCostId;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class MaintenancePlannedCost
{
    private MaintenancePlannedCostId $id;
    private string $ownerId;
    private string $vehicleId;
    private string $label;
    private ?MaintenanceEventType $eventType;
    private DateTimeImmutable $plannedFor;
    private int $plannedCostCents;
    private string $currencyCode;
    private ?string $notes;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        MaintenancePlannedCostId $id,
        string $ownerId,
        string $vehicleId,
        string $label,
        ?MaintenanceEventType $eventType,
        DateTimeImmutable $plannedFor,
        int $plannedCostCents,
        string $currencyCode,
        ?string $notes,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->ownerId = self::normalizeUuid($ownerId, 'ownerId');
        $this->vehicleId = self::normalizeUuid($vehicleId, 'vehicleId');
        $this->label = self::normalizeLabel($label);
        $this->eventType = $eventType;
        $this->plannedFor = $plannedFor;
        $this->plannedCostCents = self::normalizePositiveInt($plannedCostCents, 'plannedCostCents');
        $this->currencyCode = self::normalizeCurrencyCode($currencyCode);
        $this->notes = self::normalizeNotes($notes);
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(
        string $ownerId,
        string $vehicleId,
        string $label,
        ?MaintenanceEventType $eventType,
        DateTimeImmutable $plannedFor,
        int $plannedCostCents,
        string $currencyCode = 'EUR',
        ?string $notes = null,
        ?DateTimeImmutable $now = null,
    ): self {
        $now ??= new DateTimeImmutable();

        return new self(
            MaintenancePlannedCostId::new(),
            $ownerId,
            $vehicleId,
            $label,
            $eventType,
            $plannedFor,
            $plannedCostCents,
            $currencyCode,
            $notes,
            $now,
            $now,
        );
    }

    public static function reconstitute(
        MaintenancePlannedCostId $id,
        string $ownerId,
        string $vehicleId,
        string $label,
        ?MaintenanceEventType $eventType,
        DateTimeImmutable $plannedFor,
        int $plannedCostCents,
        string $currencyCode,
        ?string $notes,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            $ownerId,
            $vehicleId,
            $label,
            $eventType,
            $plannedFor,
            $plannedCostCents,
            $currencyCode,
            $notes,
            $createdAt,
            $updatedAt,
        );
    }

    public function update(
        string $vehicleId,
        string $label,
        ?MaintenanceEventType $eventType,
        DateTimeImmutable $plannedFor,
        int $plannedCostCents,
        string $currencyCode,
        ?string $notes,
        ?DateTimeImmutable $at = null,
    ): void {
        $at ??= new DateTimeImmutable();

        $this->vehicleId = self::normalizeUuid($vehicleId, 'vehicleId');
        $this->label = self::normalizeLabel($label);
        $this->eventType = $eventType;
        $this->plannedFor = $plannedFor;
        $this->plannedCostCents = self::normalizePositiveInt($plannedCostCents, 'plannedCostCents');
        $this->currencyCode = self::normalizeCurrencyCode($currencyCode);
        $this->notes = self::normalizeNotes($notes);
        $this->updatedAt = $at;
    }

    public function id(): MaintenancePlannedCostId
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

    public function label(): string
    {
        return $this->label;
    }

    public function eventType(): ?MaintenanceEventType
    {
        return $this->eventType;
    }

    public function plannedFor(): DateTimeImmutable
    {
        return $this->plannedFor;
    }

    public function plannedCostCents(): int
    {
        return $this->plannedCostCents;
    }

    public function currencyCode(): string
    {
        return $this->currencyCode;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function normalizeUuid(string $value, string $field): string
    {
        $normalized = trim($value);
        if ('' === $normalized || !Uuid::isValid($normalized)) {
            throw new InvalidArgumentException(sprintf('%s must be a valid UUID.', $field));
        }

        return $normalized;
    }

    private static function normalizeLabel(string $value): string
    {
        $normalized = trim($value);
        if ('' === $normalized) {
            throw new InvalidArgumentException('label must not be empty.');
        }

        return mb_substr($normalized, 0, 160);
    }

    private static function normalizePositiveInt(int $value, string $field): int
    {
        if ($value <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $field));
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

    private static function normalizeNotes(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $normalized = trim($value);

        return '' === $normalized ? null : mb_substr($normalized, 0, 2000);
    }
}
