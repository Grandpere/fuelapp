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
}
