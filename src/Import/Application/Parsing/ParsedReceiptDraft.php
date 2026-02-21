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

namespace App\Import\Application\Parsing;

use DateTimeImmutable;

final readonly class ParsedReceiptDraft
{
    /**
     * @param list<ParsedReceiptLineDraft> $lines
     * @param list<string>                 $issues
     */
    public function __construct(
        public ?string $stationName,
        public ?string $stationStreetName,
        public ?string $stationPostalCode,
        public ?string $stationCity,
        public ?DateTimeImmutable $issuedAt,
        public ?int $totalCents,
        public ?int $vatAmountCents,
        public array $lines,
        public array $issues,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $lines = [];
        foreach ($this->lines as $line) {
            $lines[] = $line->toArray();
        }

        return [
            'stationName' => $this->stationName,
            'stationStreetName' => $this->stationStreetName,
            'stationPostalCode' => $this->stationPostalCode,
            'stationCity' => $this->stationCity,
            'issuedAt' => $this->issuedAt?->format(DATE_ATOM),
            'totalCents' => $this->totalCents,
            'vatAmountCents' => $this->vatAmountCents,
            'lines' => $lines,
            'issues' => $this->issues,
            'creationPayload' => $this->buildValidatedCreationPayload(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function buildValidatedCreationPayload(): ?array
    {
        if (
            null === $this->issuedAt
            || null === $this->stationName
            || null === $this->stationStreetName
            || null === $this->stationPostalCode
            || null === $this->stationCity
        ) {
            return null;
        }

        $validLines = [];
        foreach ($this->lines as $line) {
            if (
                null === $line->fuelType
                || null === $line->quantityMilliLiters
                || null === $line->unitPriceDeciCentsPerLiter
                || null === $line->vatRatePercent
            ) {
                continue;
            }

            $validLines[] = [
                'fuelType' => $line->fuelType,
                'quantityMilliLiters' => $line->quantityMilliLiters,
                'unitPriceDeciCentsPerLiter' => $line->unitPriceDeciCentsPerLiter,
                'vatRatePercent' => $line->vatRatePercent,
            ];
        }

        if ([] === $validLines) {
            return null;
        }

        return [
            'issuedAt' => $this->issuedAt->format(DATE_ATOM),
            'stationName' => $this->stationName,
            'stationStreetName' => $this->stationStreetName,
            'stationPostalCode' => $this->stationPostalCode,
            'stationCity' => $this->stationCity,
            'lines' => $validLines,
        ];
    }
}
