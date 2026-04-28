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

use App\PublicFuelStation\Domain\Enum\PublicFuelType;
use DateTimeImmutable;
use SplFileObject;

final class PublicFuelStationCsvParser
{
    /** @return iterable<ParsedPublicFuelStation> */
    public function parseFile(string $path): iterable
    {
        $file = new SplFileObject($path, 'rb');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(';', '"', '');

        /** @var list<string>|null $headers */
        $headers = null;
        foreach ($file as $row) {
            if (!is_array($row) || [] === $row || [null] === $row) {
                continue;
            }

            /** @var list<string|null> $csvRow */
            $csvRow = array_values($row);
            if (null === $headers) {
                $headers = $this->normalizeHeaders($csvRow);

                continue;
            }

            $record = $this->combineRecord($headers, $csvRow);
            $station = $this->parseRecord($record);
            if ($station instanceof ParsedPublicFuelStation) {
                yield $station;
            }
        }
    }

    /**
     * @param list<string|null> $headers
     *
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $header) {
            $normalized[] = $this->cleanHeader((string) $header);
        }

        return $normalized;
    }

    /**
     * @param list<string>      $headers
     * @param list<string|null> $row
     *
     * @return array<string, string>
     */
    private function combineRecord(array $headers, array $row): array
    {
        $record = [];
        foreach ($headers as $index => $header) {
            if ('' === $header) {
                continue;
            }

            $record[$header] = trim((string) ($row[$index] ?? ''));
        }

        return $record;
    }

    /** @param array<string, string> $record */
    private function parseRecord(array $record): ?ParsedPublicFuelStation
    {
        $sourceId = $record['id'] ?? '';
        if ('' === $sourceId) {
            return null;
        }

        $latitude = $this->readMicroDegrees($record['latitude'] ?? '');
        $longitude = $this->readMicroDegrees($record['longitude'] ?? '');
        $sourceUpdatedAt = $this->readLatestFuelUpdate($record) ?? new DateTimeImmutable();

        return new ParsedPublicFuelStation(
            $sourceId,
            $latitude,
            $longitude,
            $record['adresse'] ?? '',
            $record['code_postal'] ?? '',
            $record['ville'] ?? '',
            $this->optional($record['pop'] ?? null),
            $this->optional($record['departement'] ?? null),
            $this->optional($record['code_departement'] ?? null),
            $this->optional($record['region'] ?? null),
            $this->optional($record['code_region'] ?? null),
            'oui' === mb_strtolower($record['automate_24_24_oui_non'] ?? ''),
            $this->readList($record['services_proposes'] ?? ''),
            $this->readFuelSnapshots($record),
            $sourceUpdatedAt,
        );
    }

    private function cleanHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = trim(mb_strtolower($header));
        $header = strtr($header, [
            'à' => 'a',
            'â' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'î' => 'i',
            'ï' => 'i',
            'ô' => 'o',
            'ù' => 'u',
            'û' => 'u',
            'ç' => 'c',
            '-' => '_',
            '/' => '_',
            '(' => '',
            ')' => '',
        ]);
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;

        return trim($header, '_');
    }

    private function readMicroDegrees(string $value): ?int
    {
        if ('' === trim($value) || !is_numeric($value)) {
            return null;
        }

        return (int) round((float) $value);
    }

    /**
     * @param array<string, string> $record
     *
     * @return array<string, array{available:bool, priceMilliEurosPerLiter:int|null, priceUpdatedAt:string|null, ruptureType:string|null, ruptureStartedAt:string|null}>
     */
    private function readFuelSnapshots(array $record): array
    {
        $available = array_fill_keys($this->readFuelTypeList($record['carburants_disponibles'] ?? ''), true);
        $temporaryOutage = array_fill_keys($this->readFuelTypeList($record['carburants_en_rupture_temporaire'] ?? ''), true);
        $definitiveOutage = array_fill_keys($this->readFuelTypeList($record['carburants_en_rupture_definitive'] ?? ''), true);
        $result = [];

        foreach (PublicFuelType::cases() as $fuelType) {
            $key = $fuelType->value;
            $sourceLabel = $this->cleanHeader($fuelType->sourceLabel());
            $price = $this->readPriceMilliEuros($record['prix_'.$sourceLabel] ?? '');
            $updatedAt = $this->readDate($record['prix_'.$sourceLabel.'_mis_a_jour_le'] ?? '');
            $ruptureType = $this->optional($record['type_rupture_'.$sourceLabel] ?? null);
            $ruptureStartedAt = $this->readDate($record['debut_rupture_'.$sourceLabel.'_si_temporaire'] ?? '');

            $result[$key] = [
                'available' => isset($available[$key]) && !isset($temporaryOutage[$key]) && !isset($definitiveOutage[$key]),
                'priceMilliEurosPerLiter' => $price,
                'priceUpdatedAt' => $updatedAt?->format(DATE_ATOM),
                'ruptureType' => $ruptureType,
                'ruptureStartedAt' => $ruptureStartedAt?->format(DATE_ATOM),
            ];
        }

        return $result;
    }

    /** @return list<string> */
    private function readFuelTypeList(string $value): array
    {
        $types = [];
        foreach ($this->readList($value, ';') as $item) {
            $fuelType = PublicFuelType::fromSourceLabel($item);
            if ($fuelType instanceof PublicFuelType) {
                $types[] = $fuelType->value;
            }
        }

        return array_values(array_unique($types));
    }

    /** @return list<string> */
    private function readList(string $value, string $secondarySeparator = ','): array
    {
        $normalized = str_replace($secondarySeparator, ',', $value);
        $items = [];
        foreach (explode(',', $normalized) as $item) {
            $trimmed = trim($item);
            if ('' !== $trimmed) {
                $items[] = $trimmed;
            }
        }

        return $items;
    }

    private function readPriceMilliEuros(string $value): ?int
    {
        $normalized = str_replace(',', '.', trim($value));
        if ('' === $normalized || !is_numeric($normalized)) {
            return null;
        }

        return (int) round(((float) $normalized) * 1000);
    }

    private function readDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }

        $date = date_create_immutable($value);

        return $date instanceof DateTimeImmutable ? $date : null;
    }

    /** @param array<string, string> $record */
    private function readLatestFuelUpdate(array $record): ?DateTimeImmutable
    {
        $latest = null;
        foreach (PublicFuelType::cases() as $fuelType) {
            $sourceLabel = $this->cleanHeader($fuelType->sourceLabel());
            $updatedAt = $this->readDate($record['prix_'.$sourceLabel.'_mis_a_jour_le'] ?? '');
            if (null !== $updatedAt && (null === $latest || $updatedAt > $latest)) {
                $latest = $updatedAt;
            }
        }

        return $latest;
    }

    private function optional(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
