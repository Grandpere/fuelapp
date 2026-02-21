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

namespace App\Import\Application\Command;

use App\Receipt\Application\Command\CreateReceiptLineCommand;
use DateTimeImmutable;

final readonly class FinalizeImportJobCommand
{
    /** @param list<CreateReceiptLineCommand>|null $lines */
    public function __construct(
        public string $importJobId,
        public ?DateTimeImmutable $issuedAt = null,
        public ?array $lines = null,
        public ?string $stationName = null,
        public ?string $stationStreetName = null,
        public ?string $stationPostalCode = null,
        public ?string $stationCity = null,
        public ?int $latitudeMicroDegrees = null,
        public ?int $longitudeMicroDegrees = null,
    ) {
    }
}
