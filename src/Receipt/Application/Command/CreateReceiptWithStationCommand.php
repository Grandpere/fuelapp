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

namespace App\Receipt\Application\Command;

use DateTimeImmutable;

final readonly class CreateReceiptWithStationCommand
{
    /** @param list<CreateReceiptLineCommand> $lines */
    public function __construct(
        public DateTimeImmutable $issuedAt,
        public array $lines,
        public string $stationName,
        public string $stationStreetName,
        public string $stationPostalCode,
        public string $stationCity,
        public ?int $latitudeMicroDegrees,
        public ?int $longitudeMicroDegrees,
        public ?string $vehicleId = null,
    ) {
    }
}
