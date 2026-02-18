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

use App\Station\Domain\ValueObject\StationId;

final class Station
{
    private StationId $id;
    private string $name;
    private string $streetName;
    private string $postalCode;
    private string $city;
    private ?int $latitudeMicroDegrees;
    private ?int $longitudeMicroDegrees;

    private function __construct(
        StationId $id,
        string $name,
        string $streetName,
        string $postalCode,
        string $city,
        ?int $latitudeMicroDegrees,
        ?int $longitudeMicroDegrees,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->streetName = $streetName;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->latitudeMicroDegrees = $latitudeMicroDegrees;
        $this->longitudeMicroDegrees = $longitudeMicroDegrees;
    }

    public static function create(
        string $name,
        string $streetName,
        string $postalCode,
        string $city,
        ?int $latitudeMicroDegrees,
        ?int $longitudeMicroDegrees,
    ): self {
        return new self(StationId::new(), $name, $streetName, $postalCode, $city, $latitudeMicroDegrees, $longitudeMicroDegrees);
    }

    public static function reconstitute(
        StationId $id,
        string $name,
        string $streetName,
        string $postalCode,
        string $city,
        ?int $latitudeMicroDegrees,
        ?int $longitudeMicroDegrees,
    ): self {
        return new self($id, $name, $streetName, $postalCode, $city, $latitudeMicroDegrees, $longitudeMicroDegrees);
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
}
