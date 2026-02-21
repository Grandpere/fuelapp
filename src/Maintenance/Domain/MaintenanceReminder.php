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

use App\Maintenance\Domain\ValueObject\MaintenanceReminderId;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class MaintenanceReminder
{
    private MaintenanceReminderId $id;
    private string $ownerId;
    private string $vehicleId;
    private string $ruleId;
    private string $dedupKey;
    private ?DateTimeImmutable $dueAtDate;
    private ?int $dueAtOdometerKilometers;
    private bool $dueByDate;
    private bool $dueByOdometer;
    private DateTimeImmutable $createdAt;

    private function __construct(
        MaintenanceReminderId $id,
        string $ownerId,
        string $vehicleId,
        string $ruleId,
        string $dedupKey,
        ?DateTimeImmutable $dueAtDate,
        ?int $dueAtOdometerKilometers,
        bool $dueByDate,
        bool $dueByOdometer,
        DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->ownerId = self::normalizeUuid($ownerId, 'ownerId');
        $this->vehicleId = self::normalizeUuid($vehicleId, 'vehicleId');
        $this->ruleId = self::normalizeUuid($ruleId, 'ruleId');
        $this->dedupKey = trim($dedupKey);
        $this->dueAtDate = $dueAtDate;
        $this->dueAtOdometerKilometers = $dueAtOdometerKilometers;
        $this->dueByDate = $dueByDate;
        $this->dueByOdometer = $dueByOdometer;
        $this->createdAt = $createdAt;
    }

    public static function create(
        string $ownerId,
        string $vehicleId,
        string $ruleId,
        ?DateTimeImmutable $dueAtDate,
        ?int $dueAtOdometerKilometers,
        bool $dueByDate,
        bool $dueByOdometer,
        ?DateTimeImmutable $now = null,
    ): self {
        $now ??= new DateTimeImmutable();

        return new self(
            MaintenanceReminderId::new(),
            $ownerId,
            $vehicleId,
            $ruleId,
            self::computeDedupKey($ruleId, $dueAtDate, $dueAtOdometerKilometers, $dueByDate, $dueByOdometer),
            $dueAtDate,
            $dueAtOdometerKilometers,
            $dueByDate,
            $dueByOdometer,
            $now,
        );
    }

    public static function reconstitute(
        MaintenanceReminderId $id,
        string $ownerId,
        string $vehicleId,
        string $ruleId,
        string $dedupKey,
        ?DateTimeImmutable $dueAtDate,
        ?int $dueAtOdometerKilometers,
        bool $dueByDate,
        bool $dueByOdometer,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $id,
            $ownerId,
            $vehicleId,
            $ruleId,
            $dedupKey,
            $dueAtDate,
            $dueAtOdometerKilometers,
            $dueByDate,
            $dueByOdometer,
            $createdAt,
        );
    }

    public function id(): MaintenanceReminderId
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

    public function ruleId(): string
    {
        return $this->ruleId;
    }

    public function dedupKey(): string
    {
        return $this->dedupKey;
    }

    public function dueAtDate(): ?DateTimeImmutable
    {
        return $this->dueAtDate;
    }

    public function dueAtOdometerKilometers(): ?int
    {
        return $this->dueAtOdometerKilometers;
    }

    public function dueByDate(): bool
    {
        return $this->dueByDate;
    }

    public function dueByOdometer(): bool
    {
        return $this->dueByOdometer;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function computeDedupKey(
        string $ruleId,
        ?DateTimeImmutable $dueAtDate,
        ?int $dueAtOdometerKilometers,
        bool $dueByDate,
        bool $dueByOdometer,
    ): string {
        $parts = [
            'rule:'.trim($ruleId),
            'date:'.($dueAtDate?->format(DATE_ATOM) ?? 'none'),
            'odometer:'.(null === $dueAtOdometerKilometers ? 'none' : (string) $dueAtOdometerKilometers),
            'by_date:'.($dueByDate ? '1' : '0'),
            'by_odo:'.($dueByOdometer ? '1' : '0'),
        ];

        return hash('sha256', implode('|', $parts));
    }

    private static function normalizeUuid(string $value, string $field): string
    {
        $normalized = trim($value);
        if ('' === $normalized || !Uuid::isValid($normalized)) {
            throw new InvalidArgumentException(sprintf('%s must be a valid UUID.', $field));
        }

        return $normalized;
    }
}
