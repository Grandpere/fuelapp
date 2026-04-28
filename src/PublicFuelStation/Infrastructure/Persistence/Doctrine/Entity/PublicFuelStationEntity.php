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

namespace App\PublicFuelStation\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'public_fuel_stations')]
#[ORM\UniqueConstraint(name: 'public_fuel_station_source_id', columns: ['source_id'])]
#[ORM\Index(name: 'public_fuel_station_location_idx', columns: ['latitude_micro_degrees', 'longitude_micro_degrees'])]
#[ORM\Index(name: 'public_fuel_station_city_idx', columns: ['city', 'postal_code'])]
class PublicFuelStationEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 40, name: 'source_id')]
    private string $sourceId;

    #[ORM\Column(type: 'integer', nullable: true, name: 'latitude_micro_degrees')]
    private ?int $latitudeMicroDegrees = null;

    #[ORM\Column(type: 'integer', nullable: true, name: 'longitude_micro_degrees')]
    private ?int $longitudeMicroDegrees = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $address = '';

    #[ORM\Column(type: 'string', length: 20, name: 'postal_code')]
    private string $postalCode = '';

    #[ORM\Column(type: 'string', length: 120)]
    private string $city = '';

    #[ORM\Column(type: 'string', length: 8, nullable: true, name: 'population_kind')]
    private ?string $populationKind = null;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    private ?string $department = null;

    #[ORM\Column(type: 'string', length: 8, nullable: true, name: 'department_code')]
    private ?string $departmentCode = null;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(type: 'string', length: 8, nullable: true, name: 'region_code')]
    private ?string $regionCode = null;

    #[ORM\Column(type: 'boolean', name: 'automate_24')]
    private bool $automate24 = false;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $services = [];

    /** @var array<string, array<string, bool|int|string|null>> */
    #[ORM\Column(type: 'json')]
    private array $fuels = [];

    #[ORM\Column(type: 'datetime_immutable', name: 'source_updated_at')]
    private DateTimeImmutable $sourceUpdatedAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'imported_at')]
    private DateTimeImmutable $importedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->sourceUpdatedAt = new DateTimeImmutable();
        $this->importedAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getLatitudeMicroDegrees(): ?int
    {
        return $this->latitudeMicroDegrees;
    }

    public function getLongitudeMicroDegrees(): ?int
    {
        return $this->longitudeMicroDegrees;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getPopulationKind(): ?string
    {
        return $this->populationKind;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function getDepartmentCode(): ?string
    {
        return $this->departmentCode;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function getRegionCode(): ?string
    {
        return $this->regionCode;
    }

    public function isAutomate24(): bool
    {
        return $this->automate24;
    }

    /** @return list<string> */
    public function getServices(): array
    {
        return $this->services;
    }

    /** @return array<string, array<string, bool|int|string|null>> */
    public function getFuels(): array
    {
        return $this->fuels;
    }

    public function getSourceUpdatedAt(): DateTimeImmutable
    {
        return $this->sourceUpdatedAt;
    }

    public function getImportedAt(): DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function setSourceId(string $sourceId): void
    {
        $this->sourceId = $sourceId;
    }

    public function setLatitudeMicroDegrees(?int $latitudeMicroDegrees): void
    {
        $this->latitudeMicroDegrees = $latitudeMicroDegrees;
    }

    public function setLongitudeMicroDegrees(?int $longitudeMicroDegrees): void
    {
        $this->longitudeMicroDegrees = $longitudeMicroDegrees;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function setPostalCode(string $postalCode): void
    {
        $this->postalCode = $postalCode;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function setPopulationKind(?string $populationKind): void
    {
        $this->populationKind = $populationKind;
    }

    public function setDepartment(?string $department): void
    {
        $this->department = $department;
    }

    public function setDepartmentCode(?string $departmentCode): void
    {
        $this->departmentCode = $departmentCode;
    }

    public function setRegion(?string $region): void
    {
        $this->region = $region;
    }

    public function setRegionCode(?string $regionCode): void
    {
        $this->regionCode = $regionCode;
    }

    public function setAutomate24(bool $automate24): void
    {
        $this->automate24 = $automate24;
    }

    /** @param list<string> $services */
    public function setServices(array $services): void
    {
        $this->services = $services;
    }

    /** @param array<string, array<string, bool|int|string|null>> $fuels */
    public function setFuels(array $fuels): void
    {
        $this->fuels = $fuels;
    }

    public function setSourceUpdatedAt(DateTimeImmutable $sourceUpdatedAt): void
    {
        $this->sourceUpdatedAt = $sourceUpdatedAt;
    }

    public function setImportedAt(DateTimeImmutable $importedAt): void
    {
        $this->importedAt = $importedAt;
    }
}
