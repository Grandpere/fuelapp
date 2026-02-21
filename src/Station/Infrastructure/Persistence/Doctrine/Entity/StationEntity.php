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

namespace App\Station\Infrastructure\Persistence\Doctrine\Entity;

use App\Station\Domain\Enum\GeocodingStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'stations')]
#[ORM\UniqueConstraint(name: 'station_identity', columns: ['name', 'street_name', 'postal_code', 'city'])]
class StationEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, name: 'street_name')]
    private string $streetName;

    #[ORM\Column(type: 'string', length: 20, name: 'postal_code')]
    private string $postalCode;

    #[ORM\Column(type: 'string', length: 100)]
    private string $city;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $latitudeMicroDegrees = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $longitudeMicroDegrees = null;

    #[ORM\Column(type: 'string', enumType: GeocodingStatus::class, length: 16, options: ['default' => 'pending'])]
    private GeocodingStatus $geocodingStatus = GeocodingStatus::PENDING;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $geocodingRequestedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $geocodedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $geocodingFailedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $geocodingLastError = null;

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

    public function getStreetName(): string
    {
        return $this->streetName;
    }

    public function setStreetName(string $streetName): void
    {
        $this->streetName = $streetName;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): void
    {
        $this->postalCode = $postalCode;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function getLatitudeMicroDegrees(): ?int
    {
        return $this->latitudeMicroDegrees;
    }

    public function setLatitudeMicroDegrees(?int $latitudeMicroDegrees): void
    {
        $this->latitudeMicroDegrees = $latitudeMicroDegrees;
    }

    public function getLongitudeMicroDegrees(): ?int
    {
        return $this->longitudeMicroDegrees;
    }

    public function setLongitudeMicroDegrees(?int $longitudeMicroDegrees): void
    {
        $this->longitudeMicroDegrees = $longitudeMicroDegrees;
    }

    public function getGeocodingStatus(): GeocodingStatus
    {
        return $this->geocodingStatus;
    }

    public function setGeocodingStatus(GeocodingStatus $geocodingStatus): void
    {
        $this->geocodingStatus = $geocodingStatus;
    }

    public function getGeocodingRequestedAt(): ?DateTimeImmutable
    {
        return $this->geocodingRequestedAt;
    }

    public function setGeocodingRequestedAt(?DateTimeImmutable $geocodingRequestedAt): void
    {
        $this->geocodingRequestedAt = $geocodingRequestedAt;
    }

    public function getGeocodedAt(): ?DateTimeImmutable
    {
        return $this->geocodedAt;
    }

    public function setGeocodedAt(?DateTimeImmutable $geocodedAt): void
    {
        $this->geocodedAt = $geocodedAt;
    }

    public function getGeocodingFailedAt(): ?DateTimeImmutable
    {
        return $this->geocodingFailedAt;
    }

    public function setGeocodingFailedAt(?DateTimeImmutable $geocodingFailedAt): void
    {
        $this->geocodingFailedAt = $geocodingFailedAt;
    }

    public function getGeocodingLastError(): ?string
    {
        return $this->geocodingLastError;
    }

    public function setGeocodingLastError(?string $geocodingLastError): void
    {
        $this->geocodingLastError = $geocodingLastError;
    }
}
