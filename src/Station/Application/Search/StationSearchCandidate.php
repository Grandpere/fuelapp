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

namespace App\Station\Application\Search;

final readonly class StationSearchCandidate
{
    public function __construct(
        public string $id,
        public string $name,
        public string $streetName,
        public string $postalCode,
        public string $city,
        public ?int $latitudeMicroDegrees,
        public ?int $longitudeMicroDegrees,
    ) {
    }

    public function label(): string
    {
        return sprintf('%s - %s, %s %s', $this->name, $this->streetName, $this->postalCode, $this->city);
    }
}
