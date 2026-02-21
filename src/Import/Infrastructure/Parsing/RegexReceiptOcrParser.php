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

        [$vatRatePercent, $vatAmountCents] = $this->extractVat($normalizedLines, $text, $totalCents);
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
        foreach ($lines as $index => $line) {
            if (!preg_match('/\b\d{5}\s+[A-Za-zÀ-ÿ]/u', $line)) {
                continue;
            }

            if (0 === $index) {
                continue;
            }

            $candidate = $lines[$index - 1];
            if ($this->isNonAddressLine($candidate)) {
                continue;
            }

            return mb_substr($candidate, 0, 255);
        }

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

            if ($this->isNonAddressLine($line)) {
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
            if (preg_match('/\b(?P<d>\d{2})[\/\.-](?P<m>\d{2})[\/\.-](?P<y>\d{2,4})(?:\s*(?:a|à)?\s*(?P<h>\d{2})[:h](?P<i>\d{2})(?::\d{2})?)?/ui', $candidate, $m)) {
                $year = (int) $m['y'];
                if ($year < 100) {
                    $year += 2000;
                }
                $hour = isset($m['h']) ? (int) $m['h'] : 0;
                $minute = isset($m['i']) ? (int) $m['i'] : 0;

                return DateTimeImmutable::createFromFormat('!Y-m-d H:i', sprintf('%04d-%02d-%02d %02d:%02d', $year, (int) $m['m'], (int) $m['d'], $hour, $minute)) ?: null;
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

        if (preg_match('/\bmontant\s+reel\b(?P<segment>.{0,80})/uis', $text, $m)) {
            $segment = (string) $m['segment'];
            if (preg_match('/(?:eur|€)\s*(?P<amount>\d+(?:[\.,]\s?\d{2}))\b/ui', $segment, $amountMatch)) {
                return $this->decimalToCents((string) $amountMatch['amount']);
            }

            if (preg_match('/(?P<amount>\d+(?:[\.,]\s?\d{2}))\s*(?:eur|€)?\b/ui', $segment, $amountMatch)) {
                return $this->decimalToCents((string) $amountMatch['amount']);
            }
        }

        return null;
    }

    /**
     * @param list<string> $lines
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function extractVat(array $lines, string $text, ?int $totalCents): array
    {
        foreach ($lines as $index => $line) {
            if (!preg_match('/\b(tva|vat)\b/ui', $line)) {
                continue;
            }

            if (!preg_match('/(?P<rate>\d{1,2})(?:[\.,]\d{1,2})?\s*%/u', $line, $rateMatch)) {
                continue;
            }

            $rate = (int) $rateMatch['rate'];
            $amount = $this->extractVatAmountFromLine($line, $rate);

            if (null === $amount) {
                foreach ([$index + 1, $index + 2] as $nextIndex) {
                    if (!array_key_exists($nextIndex, $lines)) {
                        continue;
                    }

                    $nextLine = $lines[$nextIndex];
                    if (preg_match('/(?P<amount>\d+(?:[\.,]\s?\d{2}))/u', $nextLine, $nextMatch)) {
                        $amount = $this->decimalToCents((string) $nextMatch['amount']);
                        break;
                    }
                }
            }

            if (null === $amount && null !== $totalCents && $rate > 0) {
                $amount = (int) round($totalCents - ($totalCents / (1 + ($rate / 100))));
            }

            return [$rate, $amount];
        }

        if (preg_match('/\b(tva|vat)\b(?P<segment>.{0,120})/uis', $text, $m)) {
            $segment = (string) $m['segment'];
            if (!preg_match('/(?P<rate>\d{1,2})(?:[\.,]\d{1,2})?\s*%/u', $segment, $rateMatch)) {
                return [null, null];
            }

            $rate = (int) $rateMatch['rate'];
            $amount = null;
            if (preg_match_all('/(?P<amount>\d+(?:[\.,]\s?\d{2}))/u', $segment, $amountMatches)) {
                foreach ($amountMatches['amount'] as $amountCandidate) {
                    if (!$this->looksLikeVatRateValue((string) $amountCandidate, $rate)) {
                        $amount = $this->decimalToCents((string) $amountCandidate);
                        break;
                    }
                }
            }

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

        foreach ($lines as $index => $line) {
            $fuelType = $this->extractFuelType($line);
            if (null === $fuelType) {
                continue;
            }

            $context = $line;
            $window = array_slice($lines, max(0, $index), 7);
            if ([] !== $window) {
                $context = implode(' ', $window);
            }

            $quantityMilliLiters = $this->extractQuantityMilliLiters($line, $context);
            $unitPriceDeciCentsPerLiter = $this->extractUnitPriceDeciCentsPerLiter($line, $context);

            $lineTotalCents = null;
            if (preg_match('/(?P<amount>\d+(?:[\.,]\s?\d{2}))\s*(€|eur)?\s*$/ui', $line, $m)) {
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
        $normalized = $this->normalizeDecimalString($value);
        if (!is_numeric($normalized)) {
            return null;
        }

        $float = (float) $normalized;

        return (int) round($float * 100);
    }

    private function decimalToMilliUnits(string $value): ?int
    {
        $normalized = $this->normalizeDecimalString($value);
        if (!is_numeric($normalized)) {
            return null;
        }

        $float = (float) $normalized;

        return (int) round($float * 1000);
    }

    private function decimalToDeciCentsPerLiter(string $value): ?int
    {
        $normalized = $this->normalizeDecimalString($value);
        if (!is_numeric($normalized)) {
            return null;
        }

        $float = (float) $normalized;

        return (int) round($float * 1000);
    }

    private function normalizeDecimalString(string $value): string
    {
        $normalized = trim($value);
        $normalized = preg_replace('/\s+/u', '', $normalized);
        if (!is_string($normalized)) {
            return '';
        }

        return str_replace(',', '.', $normalized);
    }

    private function extractQuantityMilliLiters(string $line, string $context): ?int
    {
        if (preg_match('/(?P<quantity>\d+(?:[\.,]\s?\d{1,3}))\s*(l|litre|litres|liter|liters)\b/ui', $line, $m)) {
            return $this->decimalToMilliUnits((string) $m['quantity']);
        }

        if (preg_match('/quantit[eé]?\s*(?:=|:)?\s*(?P<quantity>\d+(?:[\.,]\s?\d{1,3}))/ui', $context, $m)) {
            return $this->decimalToMilliUnits((string) $m['quantity']);
        }

        return null;
    }

    private function extractUnitPriceDeciCentsPerLiter(string $line, string $context): ?int
    {
        if (preg_match('/(?P<price>\d+(?:[\.,]\s?\d{2,3}))\s*(€|eur)?\s*\/\s*l\b/ui', $line, $m)) {
            return $this->decimalToDeciCentsPerLiter((string) $m['price']);
        }

        if (preg_match('/prix\s*unit\.?\s*(?:=|:)?\s*(?P<price>\d+(?:[\.,]\s?\d{2,3}))/ui', $context, $m)) {
            return $this->decimalToDeciCentsPerLiter((string) $m['price']);
        }

        if (preg_match('/prix\s*unit\.?\s*(?:=|:)?\s*(?P<major>\d)\s*(?P<minor>\d{3})\s*(?:eur|€)/ui', $context, $m)) {
            return $this->decimalToDeciCentsPerLiter(sprintf('%s.%s', $m['major'], $m['minor']));
        }

        return null;
    }

    private function isNonAddressLine(string $line): bool
    {
        return 1 === preg_match('/\b(tel|phone|carte|visa|debit|ticket|montant|auto|pompe|carburant|quantit[eé]?|prix|tva|vat)\b/ui', $line);
    }

    private function extractVatAmountFromLine(string $line, int $rate): ?int
    {
        if (!preg_match_all('/(?P<amount>\d+(?:[\.,]\s?\d{2}))/u', $line, $matches)) {
            return null;
        }

        foreach ($matches['amount'] as $amountCandidate) {
            if ($this->looksLikeVatRateValue((string) $amountCandidate, $rate)) {
                continue;
            }

            return $this->decimalToCents((string) $amountCandidate);
        }

        return null;
    }

    private function looksLikeVatRateValue(string $value, int $rate): bool
    {
        $normalized = $this->normalizeDecimalString($value);
        if (!is_numeric($normalized)) {
            return false;
        }

        return abs((float) $normalized - (float) $rate) < 0.01;
    }
}
