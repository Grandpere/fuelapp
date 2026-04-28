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

namespace App\PublicFuelStation\Application\Import;

use DateTimeImmutable;

/** @phpstan-type FuelSnapshot array{available:bool, priceMilliEurosPerLiter:int|null, priceUpdatedAt:string|null, ruptureType:string|null, ruptureStartedAt:string|null} */
final readonly class ParsedPublicFuelStation
{
    /**
     * @param list<string>                $services
     * @param array<string, FuelSnapshot> $fuels
     */
    public function __construct(
        public string $sourceId,
        public ?int $latitudeMicroDegrees,
        public ?int $longitudeMicroDegrees,
        public string $address,
        public string $postalCode,
        public string $city,
        public ?string $populationKind,
        public ?string $department,
        public ?string $departmentCode,
        public ?string $region,
        public ?string $regionCode,
        public bool $automate24,
        public array $services,
        public array $fuels,
        public ?DateTimeImmutable $sourceUpdatedAt,
    ) {
    }
}
