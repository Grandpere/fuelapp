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
use App\Maintenance\Domain\Enum\ReminderRuleTriggerMode;
use App\Maintenance\Domain\ValueObject\MaintenanceReminderRuleId;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class MaintenanceReminderRule
{
    private MaintenanceReminderRuleId $id;
    private string $ownerId;
    private string $vehicleId;
    private string $name;
    private ReminderRuleTriggerMode $triggerMode;
    private ?MaintenanceEventType $eventType;
    private ?int $intervalDays;
    private ?int $intervalKilometers;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        MaintenanceReminderRuleId $id,
        string $ownerId,
        string $vehicleId,
        string $name,
        ReminderRuleTriggerMode $triggerMode,
        ?MaintenanceEventType $eventType,
        ?int $intervalDays,
        ?int $intervalKilometers,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->ownerId = self::normalizeUuid($ownerId, 'ownerId');
        $this->vehicleId = self::normalizeUuid($vehicleId, 'vehicleId');
        $this->name = self::normalizeName($name);
        $this->triggerMode = $triggerMode;
        $this->eventType = $eventType;
        $this->intervalDays = self::normalizeNullablePositiveInt($intervalDays, 'intervalDays');
        $this->intervalKilometers = self::normalizeNullablePositiveInt($intervalKilometers, 'intervalKilometers');
        self::assertTriggerConfiguration($this->triggerMode, $this->intervalDays, $this->intervalKilometers);
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(
        string $ownerId,
        string $vehicleId,
        string $name,
        ReminderRuleTriggerMode $triggerMode,
        ?MaintenanceEventType $eventType,
        ?int $intervalDays,
        ?int $intervalKilometers,
        ?DateTimeImmutable $now = null,
    ): self {
        $now ??= new DateTimeImmutable();

        return new self(
            MaintenanceReminderRuleId::new(),
            $ownerId,
            $vehicleId,
            $name,
            $triggerMode,
            $eventType,
            $intervalDays,
            $intervalKilometers,
            $now,
            $now,
        );
    }

    public static function reconstitute(
        MaintenanceReminderRuleId $id,
        string $ownerId,
        string $vehicleId,
        string $name,
        ReminderRuleTriggerMode $triggerMode,
        ?MaintenanceEventType $eventType,
        ?int $intervalDays,
        ?int $intervalKilometers,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            $ownerId,
            $vehicleId,
            $name,
            $triggerMode,
            $eventType,
            $intervalDays,
            $intervalKilometers,
            $createdAt,
            $updatedAt,
        );
    }

    public function id(): MaintenanceReminderRuleId
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

    public function name(): string
    {
        return $this->name;
    }

    public function triggerMode(): ReminderRuleTriggerMode
    {
        return $this->triggerMode;
    }

    public function eventType(): ?MaintenanceEventType
    {
        return $this->eventType;
    }

    public function intervalDays(): ?int
    {
        return $this->intervalDays;
    }

    public function intervalKilometers(): ?int
    {
        return $this->intervalKilometers;
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

    private static function normalizeName(string $value): string
    {
        $normalized = trim($value);
        if ('' === $normalized) {
            throw new InvalidArgumentException('name must not be empty.');
        }

        return mb_substr($normalized, 0, 120);
    }

    private static function normalizeNullablePositiveInt(?int $value, string $field): ?int
    {
        if (null === $value) {
            return null;
        }

        if ($value <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $field));
        }

        return $value;
    }

    private static function assertTriggerConfiguration(ReminderRuleTriggerMode $mode, ?int $intervalDays, ?int $intervalKilometers): void
    {
        $hasDate = null !== $intervalDays;
        $hasOdometer = null !== $intervalKilometers;

        if (ReminderRuleTriggerMode::DATE === $mode && !$hasDate) {
            throw new InvalidArgumentException('DATE trigger requires intervalDays.');
        }

        if (ReminderRuleTriggerMode::ODOMETER === $mode && !$hasOdometer) {
            throw new InvalidArgumentException('ODOMETER trigger requires intervalKilometers.');
        }

        if (ReminderRuleTriggerMode::WHICHEVER_FIRST === $mode && (!$hasDate || !$hasOdometer)) {
            throw new InvalidArgumentException('WHICHEVER_FIRST trigger requires intervalDays and intervalKilometers.');
        }
    }
}
