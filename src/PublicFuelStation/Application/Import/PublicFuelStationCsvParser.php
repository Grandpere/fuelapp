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
    private const LATITUDE_100K_MAX = 9_000_000.0;
    private const LONGITUDE_100K_MAX = 18_000_000.0;
    private const SCALE_UNKNOWN = 'unknown';
    private const SCALE_100K = '100k';
    private const SCALE_MICRO = 'micro';

    /** @return iterable<ParsedPublicFuelStation> */
    public function parseFile(string $path): iterable
    {
        $file = new SplFileObject($path, 'rb');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(';', '"', '');
        $coordinateScales = $this->inferCoordinateScales($file);

        /** @var list<string>|null $headers */
        $headers = null;
        $file->rewind();
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
            $station = $this->parseRecord($record, $coordinateScales);
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

    /**
     * @param array<string, string>                   $record
     * @param array{latitude:string,longitude:string} $coordinateScales
     */
    private function parseRecord(array $record, array $coordinateScales): ?ParsedPublicFuelStation
    {
        $sourceId = $record['id'] ?? '';
        if ('' === $sourceId) {
            return null;
        }

        [$latitude, $longitude] = $this->readCoordinatePair($record['latitude'] ?? '', $record['longitude'] ?? '', $coordinateScales);
        $sourceUpdatedAt = $this->readLatestFuelUpdate($record);

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

    /**
     * @param array{latitude:string,longitude:string} $coordinateScales
     *
     * @return array{0:?int, 1:?int}
     */
    private function readCoordinatePair(string $latitude, string $longitude, array $coordinateScales): array
    {
        $parsedLatitude = $this->readRawCoordinate($latitude);
        $parsedLongitude = $this->readRawCoordinate($longitude);

        if (null === $parsedLatitude || null === $parsedLongitude) {
            return [
                $this->toMicroDegrees($parsedLatitude, self::LATITUDE_100K_MAX, $coordinateScales['latitude']),
                $this->toMicroDegrees($parsedLongitude, self::LONGITUDE_100K_MAX, $coordinateScales['longitude']),
            ];
        }

        if (
            self::SCALE_100K === $coordinateScales['latitude']
            && self::SCALE_UNKNOWN === $coordinateScales['longitude']
            && $this->isHundredKCandidate($parsedLongitude, self::LONGITUDE_100K_MAX)
        ) {
            return [
                $this->toMicroDegrees($parsedLatitude, self::LATITUDE_100K_MAX, $coordinateScales['latitude']),
                (int) round($parsedLongitude['value'] * 10),
            ];
        }

        if (
            self::SCALE_MICRO === $coordinateScales['latitude']
            && self::SCALE_UNKNOWN === $coordinateScales['longitude']
            && $this->shouldScaleAmbiguousLongitudeWithMicroLatitude($parsedLongitude)
        ) {
            return [
                $this->toMicroDegrees($parsedLatitude, self::LATITUDE_100K_MAX, $coordinateScales['latitude']),
                (int) round($parsedLongitude['value'] * 10),
            ];
        }

        if (
            self::SCALE_MICRO === $coordinateScales['longitude']
            && self::SCALE_UNKNOWN === $coordinateScales['latitude']
            && $this->isHundredKCandidate($parsedLatitude, self::LATITUDE_100K_MAX)
        ) {
            return [
                (int) round($parsedLatitude['value'] * 10),
                $this->toMicroDegrees($parsedLongitude, self::LONGITUDE_100K_MAX, $coordinateScales['longitude']),
            ];
        }

        if (
            self::SCALE_UNKNOWN === $coordinateScales['latitude']
            && self::SCALE_UNKNOWN === $coordinateScales['longitude']
            && abs($parsedLatitude['value']) > 180.0
            && abs($parsedLongitude['value']) > 180.0
            && abs($parsedLatitude['value']) <= self::LATITUDE_100K_MAX
            && abs($parsedLongitude['value']) <= self::LONGITUDE_100K_MAX
        ) {
            return [
                (int) round($parsedLatitude['value'] * 10),
                (int) round($parsedLongitude['value'] * 10),
            ];
        }

        return [
            $this->toMicroDegrees($parsedLatitude, self::LATITUDE_100K_MAX, $coordinateScales['latitude']),
            $this->toMicroDegrees($parsedLongitude, self::LONGITUDE_100K_MAX, $coordinateScales['longitude']),
        ];
    }

    /** @return array{value:float, fromDecimalDegrees:bool}|null */
    private function readRawCoordinate(string $value): ?array
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $trimmed);
        if (!is_numeric($normalized)) {
            return null;
        }

        return ['value' => (float) $normalized, 'fromDecimalDegrees' => str_contains($trimmed, '.') || str_contains($trimmed, ',')];
    }

    /** @param array{value:float, fromDecimalDegrees:bool} $coordinate */
    private function isHundredKCandidate(array $coordinate, float $hundredKMax): bool
    {
        return abs($coordinate['value']) > 180.0 && abs($coordinate['value']) <= $hundredKMax;
    }

    /** @param array{value:float, fromDecimalDegrees:bool} $coordinate */
    private function shouldScaleAmbiguousLongitudeWithMicroLatitude(array $coordinate): bool
    {
        return $coordinate['value'] > 0
            && abs($coordinate['value']) < 1_000_000.0
            && $this->isHundredKCandidate($coordinate, self::LONGITUDE_100K_MAX);
    }

    /**
     * @param array{value:float, fromDecimalDegrees:bool}|null $coordinate
     */
    private function toMicroDegrees(?array $coordinate, float $hundredKMax, string $scale): ?int
    {
        if (null === $coordinate) {
            return null;
        }

        if (abs($coordinate['value']) <= 180.0) {
            return (int) round($coordinate['value'] * 1_000_000);
        }

        if (self::SCALE_100K === $scale && abs($coordinate['value']) <= $hundredKMax) {
            return (int) round($coordinate['value'] * 10);
        }

        return (int) round($coordinate['value']);
    }

    /**
     * @return array{latitude:string,longitude:string}
     */
    private function inferCoordinateScales(SplFileObject $file): array
    {
        $headers = null;
        $latitudeHundredK = false;
        $latitudeMicro = false;
        $longitudeHundredK = false;
        $longitudeMicro = false;

        $file->rewind();
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
            $parsedLatitude = $this->readRawCoordinate($record['latitude'] ?? '');
            $this->markScaleHints($parsedLatitude, self::LATITUDE_100K_MAX, $latitudeHundredK, $latitudeMicro);

            $parsedLongitude = $this->readRawCoordinate($record['longitude'] ?? '');
            $this->markScaleHints($parsedLongitude, self::LONGITUDE_100K_MAX, $longitudeHundredK, $longitudeMicro);
        }

        return [
            'latitude' => $this->resolveScaleHint($latitudeHundredK, $latitudeMicro, true),
            'longitude' => $this->resolveScaleHint($longitudeHundredK, $longitudeMicro, false),
        ];
    }

    /**
     * @param array{value:float, fromDecimalDegrees:bool}|null $coordinate
     */
    private function markScaleHints(?array $coordinate, float $hundredKMax, bool &$hundredK, bool &$micro): void
    {
        if (null === $coordinate || abs($coordinate['value']) <= 180.0) {
            return;
        }

        if (abs($coordinate['value']) <= $hundredKMax) {
            $hundredK = true;

            return;
        }

        $micro = true;
    }

    private function resolveScaleHint(bool $hundredK, bool $micro, bool $allowHundredKHint): string
    {
        if ($allowHundredKHint && $hundredK && !$micro) {
            return self::SCALE_100K;
        }

        if ($micro && !$hundredK) {
            return self::SCALE_MICRO;
        }

        return self::SCALE_UNKNOWN;
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
