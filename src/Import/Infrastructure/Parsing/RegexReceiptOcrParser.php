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

namespace App\Import\Infrastructure\Parsing;

use App\Import\Application\Ocr\OcrExtraction;
use App\Import\Application\Parsing\ParsedReceiptDraft;
use App\Import\Application\Parsing\ParsedReceiptLineDraft;
use App\Import\Application\Parsing\ReceiptOcrParser;
use DateTimeImmutable;

final class RegexReceiptOcrParser implements ReceiptOcrParser
{
    public function parse(OcrExtraction $extraction): ParsedReceiptDraft
    {
        $text = trim($extraction->text);
        $normalizedLines = $this->normalizeLines($extraction->pages, $text);
        $issues = [];

        $stationName = $this->extractStationName($normalizedLines);
        if (null === $stationName) {
            $issues[] = 'station_name_missing';
        }

        [$postalCode, $city] = $this->extractPostalCity($normalizedLines);
        if (null === $postalCode || null === $city) {
            $issues[] = 'station_postal_city_missing';
        }

        $street = $this->extractStreet($normalizedLines);
        if (null === $street) {
            $issues[] = 'station_street_missing';
        }

        $issuedAt = $this->extractIssuedAt($normalizedLines, $text);
        if (null === $issuedAt) {
            $issues[] = 'issued_at_missing';
        }

        $totalCents = $this->extractTotalCents($text);
        if (null === $totalCents) {
            $issues[] = 'total_missing';
        }

        [$vatRatePercent, $vatAmountCents] = $this->extractVat($text, $totalCents);
        if (null === $vatRatePercent) {
            $issues[] = 'vat_rate_missing';
        }

        $lines = $this->extractFuelLines($normalizedLines, $vatRatePercent);
        if ([] === $lines) {
            $issues[] = 'fuel_lines_missing';
        }

        return new ParsedReceiptDraft(
            $stationName,
            $street,
            $postalCode,
            $city,
            $issuedAt,
            $totalCents,
            $vatAmountCents,
            $lines,
            array_values(array_unique($issues)),
        );
    }

    /**
     * @param list<string> $pages
     *
     * @return list<string>
     */
    private function normalizeLines(array $pages, string $text): array
    {
        $sourceLines = [];
        if ([] !== $pages) {
            foreach ($pages as $pageText) {
                foreach (preg_split('/\R/u', (string) $pageText) ?: [] as $line) {
                    $sourceLines[] = (string) $line;
                }
            }
        } else {
            foreach (preg_split('/\R/u', $text) ?: [] as $line) {
                $sourceLines[] = (string) $line;
            }
        }

        $lines = [];
        foreach ($sourceLines as $line) {
            $normalized = preg_replace('/\s+/u', ' ', trim($line));
            if (!is_string($normalized) || '' === $normalized) {
                continue;
            }

            $lines[] = $normalized;
        }

        return $lines;
    }

    /** @param list<string> $lines */
    private function extractStationName(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/^(ticket|receipt)/i', $line)) {
                continue;
            }

            if (preg_match('/\b(total|ttc|tva|vat)\b/i', $line) && preg_match('/\d/', $line)) {
                continue;
            }

            if (preg_match('/\d{2}[\/\.-]\d{2}[\/\.-]\d{4}/', $line)) {
                continue;
            }

            if (!preg_match('/[A-Za-zÀ-ÿ]/u', $line)) {
                continue;
            }

            return mb_substr($line, 0, 255);
        }

        return null;
    }

    /** @param list<string> $lines
     * @return array{0: ?string, 1: ?string}
     */
    private function extractPostalCity(array $lines): array
    {
        foreach ($lines as $line) {
            if (preg_match('/\b(?P<postal>\d{5})\s+(?P<city>[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'\- ]+)\b/u', $line, $matches)) {
                $postal = trim((string) $matches['postal']);
                $city = trim((string) $matches['city']);

                return [
                    '' !== $postal ? mb_substr($postal, 0, 20) : null,
                    '' !== $city ? mb_substr($city, 0, 100) : null,
                ];
            }
        }

        return [null, null];
    }

    /** @param list<string> $lines */
    private function extractStreet(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (!preg_match('/\d/', $line) || !preg_match('/[A-Za-zÀ-ÿ]/u', $line)) {
                continue;
            }

            if (preg_match('/\b\d{5}\b/', $line)) {
                continue;
            }

            if (preg_match('/\b(total|ttc|tva|vat)\b/i', $line)) {
                continue;
            }

            return mb_substr($line, 0, 255);
        }

        return null;
    }

    /** @param list<string> $lines */
    private function extractIssuedAt(array $lines, string $fullText): ?DateTimeImmutable
    {
        $candidates = array_merge($lines, [$fullText]);
        foreach ($candidates as $candidate) {
            if (preg_match('/\b(?P<d>\d{2})[\/\.-](?P<m>\d{2})[\/\.-](?P<y>\d{4})(?:\s+(?P<h>\d{2})[:h](?P<i>\d{2}))?/u', $candidate, $m)) {
                $hour = isset($m['h']) ? (int) $m['h'] : 0;
                $minute = isset($m['i']) ? (int) $m['i'] : 0;

                return DateTimeImmutable::createFromFormat('!Y-m-d H:i', sprintf('%04d-%02d-%02d %02d:%02d', (int) $m['y'], (int) $m['m'], (int) $m['d'], $hour, $minute)) ?: null;
            }

            if (preg_match('/\b(?P<y>\d{4})-(?P<m>\d{2})-(?P<d>\d{2})(?:[ T](?P<h>\d{2}):(?P<i>\d{2}))?/u', $candidate, $m)) {
                $hour = isset($m['h']) ? (int) $m['h'] : 0;
                $minute = isset($m['i']) ? (int) $m['i'] : 0;

                return DateTimeImmutable::createFromFormat('!Y-m-d H:i', sprintf('%04d-%02d-%02d %02d:%02d', (int) $m['y'], (int) $m['m'], (int) $m['d'], $hour, $minute)) ?: null;
            }
        }

        return null;
    }

    private function extractTotalCents(string $text): ?int
    {
        if (preg_match('/\b(total|ttc)\b[^0-9]*(?P<amount>\d+(?:[\.,]\d{2}))\b/ui', $text, $m)) {
            return $this->decimalToCents((string) $m['amount']);
        }

        return null;
    }

    /** @return array{0: ?int, 1: ?int} */
    private function extractVat(string $text, ?int $totalCents): array
    {
        if (preg_match('/\b(tva|vat)\b[^0-9]*(?P<rate>\d{1,2})(?:[\.,]\d)?\s*%[^0-9]*(?P<amount>\d+(?:[\.,]\d{2}))?/ui', $text, $m)) {
            $rate = (int) $m['rate'];
            $amount = array_key_exists('amount', $m) ? $this->decimalToCents((string) $m['amount']) : null;

            if (null === $amount && null !== $totalCents && $rate > 0) {
                $amount = (int) round($totalCents - ($totalCents / (1 + ($rate / 100))));
            }

            return [$rate, $amount];
        }

        return [null, null];
    }

    /** @param list<string> $lines
     * @return list<ParsedReceiptLineDraft>
     */
    private function extractFuelLines(array $lines, ?int $fallbackVatRate): array
    {
        $result = [];

        foreach ($lines as $line) {
            $fuelType = $this->extractFuelType($line);
            if (null === $fuelType) {
                continue;
            }

            $quantityMilliLiters = null;
            if (preg_match('/(?P<quantity>\d+(?:[\.,]\d{1,3}))\s*(l|litre|litres|liter|liters)\b/ui', $line, $m)) {
                $quantityMilliLiters = $this->decimalToMilliUnits((string) $m['quantity']);
            }

            $unitPriceDeciCentsPerLiter = null;
            if (preg_match('/(?P<price>\d+(?:[\.,]\d{2,3}))\s*(€|eur)?\s*\/\s*l\b/ui', $line, $m)) {
                $unitPriceDeciCentsPerLiter = $this->decimalToDeciCentsPerLiter((string) $m['price']);
            }

            $lineTotalCents = null;
            if (preg_match('/(?P<amount>\d+(?:[\.,]\d{2}))\s*(€|eur)?\s*$/ui', $line, $m)) {
                $lineTotalCents = $this->decimalToCents((string) $m['amount']);
            }

            $vatRatePercent = $fallbackVatRate;
            if (preg_match('/\b(?P<rate>\d{1,2})\s*%\b/u', $line, $m)) {
                $vatRatePercent = (int) $m['rate'];
            }

            $result[] = new ParsedReceiptLineDraft(
                $fuelType,
                $quantityMilliLiters,
                $unitPriceDeciCentsPerLiter,
                $lineTotalCents,
                $vatRatePercent,
            );
        }

        return $result;
    }

    private function extractFuelType(string $line): ?string
    {
        $lower = mb_strtolower($line);

        return match (true) {
            str_contains($lower, 'diesel'), str_contains($lower, 'gazole') => 'diesel',
            str_contains($lower, 'sp95'), str_contains($lower, 'e10') => 'sp95',
            str_contains($lower, 'sp98') => 'sp98',
            str_contains($lower, 'gpl'), str_contains($lower, 'lpg') => 'gpl',
            default => null,
        };
    }

    private function decimalToCents(string $value): ?int
    {
        $normalized = str_replace(',', '.', trim($value));
        if (!is_numeric($normalized)) {
            return null;
        }

        $float = (float) $normalized;

        return (int) round($float * 100);
    }

    private function decimalToMilliUnits(string $value): ?int
    {
        $normalized = str_replace(',', '.', trim($value));
        if (!is_numeric($normalized)) {
            return null;
        }

        $float = (float) $normalized;

        return (int) round($float * 1000);
    }

    private function decimalToDeciCentsPerLiter(string $value): ?int
    {
        $normalized = str_replace(',', '.', trim($value));
        if (!is_numeric($normalized)) {
            return null;
        }

        $float = (float) $normalized;

        return (int) round($float * 1000);
    }
}
