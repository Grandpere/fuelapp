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

namespace App\Station\Domain;

use App\Station\Domain\Enum\GeocodingStatus;
use App\Station\Domain\ValueObject\StationId;
use DateTimeImmutable;

final class Station
{
    private StationId $id;
    private string $name;
    private string $streetName;
    private string $postalCode;
    private string $city;
    private ?int $latitudeMicroDegrees;
    private ?int $longitudeMicroDegrees;
    private GeocodingStatus $geocodingStatus;
    private ?DateTimeImmutable $geocodingRequestedAt;
    private ?DateTimeImmutable $geocodedAt;
    private ?DateTimeImmutable $geocodingFailedAt;
    private ?string $geocodingLastError;

    private function __construct(
        StationId $id,
        string $name,
        string $streetName,
        string $postalCode,
        string $city,
        ?int $latitudeMicroDegrees,
        ?int $longitudeMicroDegrees,
        GeocodingStatus $geocodingStatus,
        ?DateTimeImmutable $geocodingRequestedAt,
        ?DateTimeImmutable $geocodedAt,
        ?DateTimeImmutable $geocodingFailedAt,
        ?string $geocodingLastError,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->streetName = $streetName;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->latitudeMicroDegrees = $latitudeMicroDegrees;
        $this->longitudeMicroDegrees = $longitudeMicroDegrees;
        $this->geocodingStatus = $geocodingStatus;
        $this->geocodingRequestedAt = $geocodingRequestedAt;
        $this->geocodedAt = $geocodedAt;
        $this->geocodingFailedAt = $geocodingFailedAt;
        $this->geocodingLastError = $geocodingLastError;
    }

    public static function create(
        string $name,
        string $streetName,
        string $postalCode,
        string $city,
        ?int $latitudeMicroDegrees,
        ?int $longitudeMicroDegrees,
    ): self {
        $now = new DateTimeImmutable();
        $hasCoordinates = null !== $latitudeMicroDegrees && null !== $longitudeMicroDegrees;

        return new self(
            StationId::new(),
            $name,
            $streetName,
            $postalCode,
            $city,
            $latitudeMicroDegrees,
            $longitudeMicroDegrees,
            $hasCoordinates ? GeocodingStatus::SUCCESS : GeocodingStatus::PENDING,
            $hasCoordinates ? null : $now,
            $hasCoordinates ? $now : null,
            null,
            null,
        );
    }

    public static function reconstitute(
        StationId $id,
        string $name,
        string $streetName,
        string $postalCode,
        string $city,
        ?int $latitudeMicroDegrees,
        ?int $longitudeMicroDegrees,
        GeocodingStatus $geocodingStatus = GeocodingStatus::PENDING,
        ?DateTimeImmutable $geocodingRequestedAt = null,
        ?DateTimeImmutable $geocodedAt = null,
        ?DateTimeImmutable $geocodingFailedAt = null,
        ?string $geocodingLastError = null,
    ): self {
        return new self(
            $id,
            $name,
            $streetName,
            $postalCode,
            $city,
            $latitudeMicroDegrees,
            $longitudeMicroDegrees,
            $geocodingStatus,
            $geocodingRequestedAt,
            $geocodedAt,
            $geocodingFailedAt,
            $geocodingLastError,
        );
    }

    public function id(): StationId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function streetName(): string
    {
        return $this->streetName;
    }

    public function postalCode(): string
    {
        return $this->postalCode;
    }

    public function city(): string
    {
        return $this->city;
    }

    public function latitudeMicroDegrees(): ?int
    {
        return $this->latitudeMicroDegrees;
    }

    public function longitudeMicroDegrees(): ?int
    {
        return $this->longitudeMicroDegrees;
    }

    public function geocodingStatus(): GeocodingStatus
    {
        return $this->geocodingStatus;
    }

    public function geocodingRequestedAt(): ?DateTimeImmutable
    {
        return $this->geocodingRequestedAt;
    }

    public function geocodedAt(): ?DateTimeImmutable
    {
        return $this->geocodedAt;
    }

    public function geocodingFailedAt(): ?DateTimeImmutable
    {
        return $this->geocodingFailedAt;
    }

    public function geocodingLastError(): ?string
    {
        return $this->geocodingLastError;
    }

    public function markGeocodingPending(?DateTimeImmutable $requestedAt = null): void
    {
        $this->geocodingStatus = GeocodingStatus::PENDING;
        $this->geocodingRequestedAt = $requestedAt ?? new DateTimeImmutable();
        $this->geocodingFailedAt = null;
        $this->geocodingLastError = null;
    }

    public function markGeocodingSuccess(int $latitudeMicroDegrees, int $longitudeMicroDegrees, ?DateTimeImmutable $geocodedAt = null): void
    {
        $this->latitudeMicroDegrees = $latitudeMicroDegrees;
        $this->longitudeMicroDegrees = $longitudeMicroDegrees;
        $this->geocodingStatus = GeocodingStatus::SUCCESS;
        $this->geocodedAt = $geocodedAt ?? new DateTimeImmutable();
        $this->geocodingFailedAt = null;
        $this->geocodingLastError = null;
    }

    public function markGeocodingFailed(string $error, ?DateTimeImmutable $failedAt = null): void
    {
        $this->geocodingStatus = GeocodingStatus::FAILED;
        $this->geocodingFailedAt = $failedAt ?? new DateTimeImmutable();
        $this->geocodingLastError = $error;
    }
}
