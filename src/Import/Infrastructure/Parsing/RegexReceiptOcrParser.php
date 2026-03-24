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

        $stationName = $this->extractStationName($normalizedLines, $text);
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
        $lines = $this->extractFuelLines($normalizedLines, $vatRatePercent, $totalCents);
        if (null === $vatRatePercent && !$this->hasVatRateOnAnyFuelLine($lines)) {
            $issues[] = 'vat_rate_missing';
        }

        if ([] === $lines) {
            $issues[] = 'fuel_lines_missing';
        } else {
            $hasCompleteLine = false;
            foreach ($lines as $line) {
                if (null === $line->quantityMilliLiters) {
                    $issues[] = 'fuel_line_quantity_missing';
                }

                if (null === $line->unitPriceDeciCentsPerLiter) {
                    $issues[] = 'fuel_line_unit_price_missing';
                }

                if (null === $line->vatRatePercent) {
                    $issues[] = 'fuel_line_vat_rate_missing';
                }

                if (
                    null !== $line->quantityMilliLiters
                    && null !== $line->unitPriceDeciCentsPerLiter
                    && null !== $line->vatRatePercent
                ) {
                    $hasCompleteLine = true;
                }
            }

            if (!$hasCompleteLine) {
                $issues[] = 'fuel_lines_incomplete';
            }
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
                $normalizedPageText = $this->normalizeLineBreakTokens((string) $pageText);
                foreach (preg_split('/\R/u', $normalizedPageText) ?: [] as $line) {
                    $sourceLines[] = (string) $line;
                }
            }
        } else {
            $normalizedText = $this->normalizeLineBreakTokens($text);
            foreach (preg_split('/\R/u', $normalizedText) ?: [] as $line) {
                $sourceLines[] = (string) $line;
            }
        }

        $lines = [];
        foreach ($sourceLines as $line) {
            $segments = preg_split('/\t+/u', $line) ?: [$line];
            foreach ($segments as $segment) {
                $normalized = preg_replace('/\s+/u', ' ', trim((string) $segment));
                if (!is_string($normalized) || '' === $normalized) {
                    continue;
                }

                $lines[] = $normalized;
            }
        }

        return $lines;
    }

    private function normalizeLineBreakTokens(string $value): string
    {
        return str_replace(['\\r\\n', '\\n', '\\r'], "\n", $value);
    }

    /** @param list<string> $lines */
    private function extractStationName(array $lines, string $fullText): ?string
    {
        $bestCandidate = null;
        $bestScore = -1000;

        foreach ($lines as $line) {
            $candidate = $this->cleanStationCandidate($line);
            if (null === $candidate) {
                continue;
            }

            if (preg_match('/^(ticket|receipt)/i', $line)) {
                continue;
            }

            if (preg_match('/\b(total|ttc|tva|vat)\b/i', $line) && preg_match('/\d/', $line)) {
                continue;
            }

            if (preg_match('/\d{2}[\/\.-]\d{2}[\/\.-]\d{2,4}/', $line)) {
                continue;
            }

            if (null !== $this->extractFuelType($candidate)) {
                continue;
            }

            if (preg_match('/\b(?:[A-Za-z]-)?\d{4,5}\s+[A-Za-zÀ-ÿ]/u', $candidate)) {
                continue;
            }

            if (!preg_match('/[A-Za-zÀ-ÿ]/u', $candidate)) {
                continue;
            }

            if (!preg_match('/^[A-Za-zÀ-ÿ]/u', $candidate)) {
                continue;
            }

            $score = $this->scoreStationCandidate($candidate);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCandidate = $candidate;
            }
        }

        if (is_string($bestCandidate) && $bestScore >= 0) {
            return mb_substr($this->normalizeStationName($bestCandidate), 0, 255);
        }

        if (preg_match('/\b(?P<name>petro est leclerc|intermarche(?:\s+\d+)?|total(?:energies)?|e leclerc)\b/ui', $fullText, $matches)) {
            $candidate = trim((string) $matches['name']);
            if ('' !== $candidate) {
                return mb_substr($this->normalizeStationName($candidate), 0, 255);
            }
        }

        return null;
    }

    /** @param list<string> $lines
     * @return array{0: ?string, 1: ?string}
     */
    private function extractPostalCity(array $lines): array
    {
        foreach ($lines as $line) {
            if (!preg_match('/\b(?P<postal>(?:[A-Za-z]-)?\d{4,5})\s+(?P<city>[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'\- ]{1,100})\b/u', $line, $matches)) {
                continue;
            }

            $postal = strtoupper(trim((string) $matches['postal']));
            $city = $this->sanitizeCity((string) $matches['city']);
            if (null === $city) {
                continue;
            }

            return [
                '' !== $postal ? mb_substr($postal, 0, 20) : null,
                mb_substr($city, 0, 100),
            ];
        }

        foreach ($lines as $index => $line) {
            if (!preg_match('/^\s*(?P<postal>(?:[A-Za-z]-)?\d{4,5})\s*$/u', $line, $postalMatch)) {
                continue;
            }

            $postal = strtoupper(trim((string) $postalMatch['postal']));
            foreach ([$index + 1, $index - 1] as $cityIndex) {
                if (!isset($lines[$cityIndex])) {
                    continue;
                }

                $city = $this->sanitizeCity($lines[$cityIndex]);
                if (null === $city || !$this->looksLikeCityCandidate($city)) {
                    continue;
                }

                return [
                    '' !== $postal ? mb_substr($postal, 0, 20) : null,
                    mb_substr($city, 0, 100),
                ];
            }
        }

        foreach ($lines as $line) {
            $city = $this->sanitizeCity($line);
            if (null === $city || !$this->looksLikeCityCandidate($city)) {
                continue;
            }

            return [null, mb_substr($city, 0, 100)];
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

            $prefixedStreet = $this->mergeNumericStreetPrefixBeforePostalCity($lines, $index, $candidate);
            if (null !== $prefixedStreet) {
                return mb_substr($prefixedStreet, 0, 255);
            }

            $merged = $this->mergeSplitStreetLineBeforePostalCity($lines, $index, $candidate);
            if (null !== $merged) {
                return mb_substr($merged, 0, 255);
            }

            if ($this->looksLikeAddressCandidate($candidate)) {
                return mb_substr($candidate, 0, 255);
            }

            for ($offset = 2; $offset <= 4; ++$offset) {
                $scanIndex = $index - $offset;
                if ($scanIndex < 0 || !isset($lines[$scanIndex])) {
                    break;
                }

                $fallbackCandidate = $lines[$scanIndex];
                if ($this->isNonAddressLine($fallbackCandidate)) {
                    continue;
                }

                if ($this->looksLikeAddressCandidate($fallbackCandidate)) {
                    return mb_substr($fallbackCandidate, 0, 255);
                }
            }
        }

        foreach ($lines as $line) {
            if (preg_match('/\bstation\s+dac\b/ui', $line, $matches, PREG_OFFSET_CAPTURE)) {
                return 'STATION DAC';
            }

            $inlineStreet = $this->extractInlineStreetBeforePostalCity($line);
            if (null !== $inlineStreet) {
                return mb_substr($inlineStreet, 0, 255);
            }

            if (!preg_match('/[A-Za-zÀ-ÿ]/u', $line)) {
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

            $prefixedStreet = $this->mergeNumericStreetPrefixInStandaloneStreetSequence($lines, $line);
            if (null !== $prefixedStreet) {
                return mb_substr($prefixedStreet, 0, 255);
            }

            if ($this->looksLikeAddressCandidate($line)) {
                return mb_substr($line, 0, 255);
            }
        }

        foreach ($lines as $line) {
            if ($this->isNonAddressLine($line)) {
                continue;
            }

            if ($this->looksLikeLocationAliasCandidate($line)) {
                return mb_substr($line, 0, 255);
            }
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

        if (preg_match('/(?:eur|€)\s*(?P<amount>\d+(?:[\.,]\s?\d{2}))(?P<segment>.{0,32})\bmontant\s+reel\b/uis', $text, $m)) {
            return $this->decimalToCents((string) $m['amount']);
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
        $compactVat = $this->extractVatFromCompactAmountSequence($text, $totalCents);
        if (null !== $compactVat) {
            return $compactVat;
        }

        foreach ($lines as $index => $line) {
            if (!preg_match('/\b(tva|vat)\b/ui', $line)) {
                continue;
            }

            $joinedSegment = implode(' ', array_slice($lines, $index, 5));
            $splitRate = $this->extractSplitVatRate($joinedSegment);
            if (null !== $splitRate) {
                $amount = $this->extractSplitVatAmount($joinedSegment, $splitRate, $totalCents);
                if (null === $amount && null !== $totalCents && $splitRate > 0) {
                    $amount = (int) round($totalCents - ($totalCents / (1 + ($splitRate / 100))));
                }

                return [$splitRate, $amount];
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
    private function extractFuelLines(array $lines, ?int $fallbackVatRate, ?int $receiptTotalCents): array
    {
        $result = [];

        foreach ($lines as $index => $line) {
            $fuelType = $this->extractFuelType($line);
            if (null === $fuelType) {
                continue;
            }

            $context = $line;
            $windowStart = max(0, $index - 6);
            $windowLength = min(count($lines) - $windowStart, 14);
            $window = array_slice($lines, $windowStart, $windowLength);
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

            if (null === $vatRatePercent && preg_match('/\barticle\b.*\btva\b.*\bmontant\b/ui', $context)) {
                if (preg_match('/\b(?:diesel|gazole|sp95|sp98|e10|excellium\s*98)\b.*?\b(?P<rate>\d{1,2})\b\s*(?:€|eur)?\s*\d+(?:[\.,]\d{2})/ui', $context, $rateMatch)) {
                    $candidateRate = (int) $rateMatch['rate'];
                    if ($candidateRate > 0 && $candidateRate <= 30) {
                        $vatRatePercent = $candidateRate;
                    }
                }
            }

            if (null === $quantityMilliLiters && null !== $unitPriceDeciCentsPerLiter) {
                $totalForInference = $lineTotalCents ?? $receiptTotalCents;
                if (null !== $totalForInference) {
                    $quantityMilliLiters = $this->inferQuantityFromTotalAndUnitPrice($totalForInference, $unitPriceDeciCentsPerLiter);
                }
            }

            [$quantityMilliLiters, $unitPriceDeciCentsPerLiter] = $this->inferFuelMetricsFromContext(
                $context,
                $lineTotalCents ?? $receiptTotalCents,
                $quantityMilliLiters,
                $unitPriceDeciCentsPerLiter,
            );

            if (null === $quantityMilliLiters && null === $unitPriceDeciCentsPerLiter && null === $lineTotalCents) {
                continue;
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
            str_contains($lower, 'excellium 98') => 'sp98',
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
            return $this->toPlausibleQuantityMilliLiters((string) $m['quantity']);
        }

        if (preg_match('/quantit[eé]?\s*(?:=|:)?\s*(?P<quantity>\d+(?:[\.,]\s?\d{1,3}))/ui', $context, $m)) {
            return $this->toPlausibleQuantityMilliLiters((string) $m['quantity']);
        }

        if (preg_match('/\bvolume\b.{0,40}?(?P<quantity>\d+(?:[\.,]\s?\d{1,3}))/ui', $context, $m)) {
            return $this->toPlausibleQuantityMilliLiters((string) $m['quantity']);
        }

        if (preg_match('/\b(col|pompe)\b[^()]*\((?P<quantity>\d+(?:[\.,]\s?\d{1,3}))\s*l\b/ui', $context, $m)) {
            return $this->toPlausibleQuantityMilliLiters((string) $m['quantity']);
        }

        if (preg_match('/\((?:[^)]*?)(?P<quantity>\d+(?:[\.,]\s?\d{1,3}))\s*l\b/ui', $context, $m)) {
            return $this->toPlausibleQuantityMilliLiters((string) $m['quantity']);
        }

        if (preg_match('/\b(eur|€|total|ttc|tva|vat|montant|brut|net)\b/ui', $line)) {
            return null;
        }

        if (preg_match('/(?P<quantity>\d+(?:[\.,]\s?\d{1,3}))\s*[^\d\s]{1,3}\s*$/u', $line, $m)) {
            return $this->toPlausibleQuantityMilliLiters((string) $m['quantity']);
        }

        return null;
    }

    private function extractUnitPriceDeciCentsPerLiter(string $line, string $context): ?int
    {
        if (preg_match('/(?P<price>\d+(?:[\.,]\s?\d{2,3}))\s*(€|eur)?\s*\/\s*l\b/ui', $line, $m)) {
            return $this->toPlausibleUnitPrice((string) $m['price']);
        }

        if (preg_match('/prix\s*unit\.?\s*(?:=|:)?\s*(?P<price>\d+(?:[\.,]\s?\d{2,3}))/ui', $context, $m)) {
            return $this->toPlausibleUnitPrice((string) $m['price']);
        }

        if (preg_match('/(?P<price>\d+(?:[\.,]\s?\d{2,3}))\s*\/\s*[l18]\b/ui', $context, $m)) {
            return $this->toPlausibleUnitPrice((string) $m['price']);
        }

        if (preg_match('/(?P<major>\d)\s*[\.,]\s*(?P<minor>\d{3})\s*\/\s*[l8]/ui', $context, $m)) {
            return $this->toPlausibleUnitPrice(sprintf('%s.%s', $m['major'], $m['minor']));
        }

        if (preg_match('/prix\s*unit\.?\s*(?:=|:)?\s*(?P<major>\d)\s*(?P<minor>\d{3})\s*(?:eur|€)/ui', $context, $m)) {
            return $this->toPlausibleUnitPrice(sprintf('%s.%s', $m['major'], $m['minor']));
        }

        if (preg_match('/prix\s*unit\.?\s*(?:=|:)?\s*(?P<minor>\d{3})\s*(?:eur|€)/ui', $context, $m)) {
            return $this->toPlausibleUnitPrice(sprintf('1.%s', $m['minor']));
        }

        if (preg_match('/\bprix\b(?P<segment>.{0,64})/ui', $context, $m)) {
            $segment = (string) $m['segment'];

            if (preg_match('/(?P<major>\d)\s+(?P<minor>\d{3})\b/u', $segment, $splitDigits)) {
                $candidate = $this->toPlausibleUnitPrice(sprintf('%s.%s', $splitDigits['major'], $splitDigits['minor']));
                if (null !== $candidate) {
                    return $candidate;
                }
            }

            if (preg_match_all('/(?P<price>\d+(?:[\.,]\s?\d{2,3}))/u', $segment, $matches)) {
                foreach ($matches['price'] as $price) {
                    $candidate = $this->toPlausibleUnitPrice((string) $price);
                    if (null !== $candidate) {
                        return $candidate;
                    }
                }
            }
        }

        if (preg_match('/\b(?P<minor>\d{3})\s*(?:eur|€)\s*(?:l|litre|litres|liter|liters)\b/ui', $context, $m)) {
            return $this->toPlausibleUnitPrice(sprintf('1.%s', $m['minor']));
        }

        return null;
    }

    private function toPlausibleUnitPrice(string $value): ?int
    {
        $price = $this->decimalToDeciCentsPerLiter($value);
        if (null === $price) {
            return null;
        }

        // Fuel unit prices outside this range are usually OCR noise or totals.
        if ($price < 500 || $price > 5000) {
            return null;
        }

        return $price;
    }

    private function isNonAddressLine(string $line): bool
    {
        return 1 === preg_match('/\b(tel|phone|carte|visa|debit|ticket|montant|auto|pompe|carburant|quantit[eé]?|prix|tva|vat|siret|naf|logiciel|terminal|contrat|euro|euros|centimes?|remise|etat|dac|vl|bonjour|a[0-9]{8,})\b/ui', $line);
    }

    private function extractInlineStreetBeforePostalCity(string $line): ?string
    {
        if (!preg_match('/(?P<prefix>.+?)\s+(?P<postal>(?:[A-Za-z]-)?\d{4,5})\s+(?P<city>[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'\- ]{1,100})\b/u', $line, $matches)) {
            return null;
        }

        $prefix = trim((string) $matches['prefix']);
        if ('' === $prefix) {
            return null;
        }

        $prefix = preg_replace('/\b(?:tel|phone|carte|visa|debit|ticket|montant|tva|vat|comptant|auto|pompe|carburant|quantit[eé]?|prix)\b.*$/ui', '', $prefix);
        if (!is_string($prefix)) {
            return null;
        }

        $prefix = preg_replace('/\b\d{6,}\b/u', ' ', $prefix);
        if (!is_string($prefix)) {
            return null;
        }

        $prefix = preg_replace('/\s+/u', ' ', trim($prefix));
        if (!is_string($prefix) || '' === $prefix) {
            return null;
        }

        if (!preg_match('/(?P<candidate>[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'\- ]{3,80})$/u', $prefix, $candidateMatch)) {
            return null;
        }

        $candidate = trim((string) $candidateMatch['candidate']);
        $words = preg_split('/\s+/u', $candidate) ?: [];
        if (count($words) > 3) {
            for ($tailSize = 3; $tailSize <= min(5, count($words)); ++$tailSize) {
                $tailCandidate = implode(' ', array_slice($words, -$tailSize));
                if ($tailCandidate === $candidate) {
                    continue;
                }

                if ($this->looksLikeAddressCandidate($tailCandidate) || $this->looksLikeLocationAliasCandidate($tailCandidate)) {
                    $candidate = $tailCandidate;
                    break;
                }
            }
        }

        if ($this->isNonAddressLine($candidate)) {
            return null;
        }

        if (!$this->looksLikeAddressCandidate($candidate) && !$this->looksLikeLocationAliasCandidate($candidate)) {
            return null;
        }

        return $candidate;
    }

    /**
     * @param list<string> $lines
     */
    private function mergeSplitStreetLineBeforePostalCity(array $lines, int $postalCityIndex, string $streetTail): ?string
    {
        if ($postalCityIndex < 2) {
            return null;
        }

        $streetHead = $lines[$postalCityIndex - 2];
        if ($this->isNonAddressLine($streetHead) || $this->isNonAddressLine($streetTail)) {
            return null;
        }

        if (preg_match('/\b\d{5}\b/u', $streetHead) || preg_match('/\b\d{5}\b/u', $streetTail)) {
            return null;
        }

        if (preg_match('/\b\d{2}[\/\.-]\d{2}[\/\.-]\d{2,4}\b/u', $streetHead) || preg_match('/\b\d{2}[\/\.-]\d{2}[\/\.-]\d{2,4}\b/u', $streetTail)) {
            return null;
        }

        if (!$this->looksLikeStreetHead($streetHead)) {
            return null;
        }

        if (!preg_match('/[A-Za-zÀ-ÿ]/u', $streetTail)) {
            return null;
        }

        return trim(sprintf('%s %s', $streetHead, $streetTail));
    }

    private function looksLikeStreetHead(string $line): bool
    {
        return 1 === preg_match('/\b(rue|route|avenue|av\.?|boulevard|bd\.?|chemin|all[ée]e|impasse|place|quai|voie|faubourg|lotissement|aire|r[dn]\d*)\b/ui', $line);
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

    /**
     * @return array{0: int, 1: int}|null
     */
    private function extractVatFromCompactAmountSequence(string $text, ?int $totalCents): ?array
    {
        if (null === $totalCents) {
            return null;
        }

        if (!preg_match('/(?P<net>\d+(?:[\.,]\d{2}))\s+(?P<vat>\d+(?:[\.,]\d{2}))\s+(?P<rate>\d{1,2}(?:[\.,]\d{1,2})?)\s+€?\s*(?P<total>\d+(?:[\.,]\d{2}))/u', $text, $m)) {
            return null;
        }

        $netCents = $this->decimalToCents((string) $m['net']);
        $vatCents = $this->decimalToCents((string) $m['vat']);
        $candidateTotalCents = $this->decimalToCents((string) $m['total']);
        if (null === $netCents || null === $vatCents || null === $candidateTotalCents) {
            return null;
        }

        if (abs($candidateTotalCents - $totalCents) > 2) {
            return null;
        }

        $rateRaw = $this->normalizeDecimalString((string) $m['rate']);
        if (!is_numeric($rateRaw)) {
            return null;
        }

        $rate = (int) round((float) $rateRaw);
        if ($rate <= 0 || $rate > 30) {
            return null;
        }

        return [$rate, $vatCents];
    }

    private function cleanStationCandidate(string $line): ?string
    {
        $normalized = trim($line);
        if ('' === $normalized) {
            return null;
        }

        $cutPatterns = [
            '/\bcarte\s+bancaire\b/ui',
            '/\ba000[0-9a-z]+\b/ui',
            '/\bvisa\b/ui',
            '/\bticket\b/ui',
            '/\bmontant\b/ui',
            '/\bdebit\b/ui',
            '/\btva\b/ui',
            '/\bdate\b/ui',
        ];
        foreach ($cutPatterns as $pattern) {
            if (preg_match($pattern, $normalized, $match, PREG_OFFSET_CAPTURE)) {
                $offset = (int) $match[0][1];
                if (0 === $offset) {
                    return null;
                }

                if ($offset > 0) {
                    $normalized = trim(substr($normalized, 0, $offset));
                }
                break;
            }
        }

        if ('' === $normalized) {
            return null;
        }

        return $normalized;
    }

    private function sanitizeCity(string $city): ?string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($city));
        if (!is_string($normalized) || '' === $normalized) {
            return null;
        }

        $normalized = preg_replace('/\b(?:carte|bancaire|visa|comptant|ticket|debit|montant|tva|total|tel|siret|naf|terminal|contrat)\b.*$/ui', '', $normalized);
        if (!is_string($normalized)) {
            return null;
        }

        $normalized = trim($normalized, " \t\n\r\0\x0B-");
        if ('' === $normalized) {
            return null;
        }

        $brandCutMatch = preg_match('/\b(petro|leclerc|intermarche|total|energies)\b/ui', $normalized, $match, PREG_OFFSET_CAPTURE);
        if (1 === $brandCutMatch) {
            $offset = (int) $match[0][1];
            if ($offset > 0) {
                $candidate = trim(substr($normalized, 0, $offset));
                if ('' !== $candidate) {
                    $normalized = $candidate;
                }
            }
        }

        return $normalized;
    }

    private function looksLikeAddressCandidate(string $line): bool
    {
        if (preg_match('/\b(euro|euros|centimes?|remise|etat|litre|litres|beneficiez)\b/ui', $line)) {
            return false;
        }

        if ($this->looksLikeStreetHead($line)) {
            return true;
        }

        if (1 === preg_match('/^\d{1,4}\s+[A-Za-zÀ-ÿ]/u', $line)) {
            return true;
        }

        return false;
    }

    private function toPlausibleQuantityMilliLiters(string $value): ?int
    {
        $quantity = $this->decimalToMilliUnits($value);
        if (null === $quantity) {
            return null;
        }

        if ($quantity < 500 || $quantity > 200_000) {
            return null;
        }

        return $quantity;
    }

    private function inferQuantityFromTotalAndUnitPrice(int $totalCents, int $unitPriceDeciCentsPerLiter): ?int
    {
        if ($totalCents <= 0 || $unitPriceDeciCentsPerLiter <= 0) {
            return null;
        }

        $quantityMilliLiters = (int) round(($totalCents * 10000) / $unitPriceDeciCentsPerLiter);
        $quantityMilliLiters = (int) (round($quantityMilliLiters / 10) * 10);
        if ($quantityMilliLiters < 500 || $quantityMilliLiters > 200_000) {
            return null;
        }

        return $quantityMilliLiters;
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function inferFuelMetricsFromContext(string $context, ?int $totalCents, ?int $quantityMilliLiters, ?int $unitPriceDeciCentsPerLiter): array
    {
        if (null === $totalCents || $totalCents <= 0) {
            return [$quantityMilliLiters, $unitPriceDeciCentsPerLiter];
        }

        $quantityCandidates = [];
        $priceCandidates = [];

        if (null !== $quantityMilliLiters) {
            $quantityCandidates[] = $quantityMilliLiters;
        }

        if (null !== $unitPriceDeciCentsPerLiter) {
            $priceCandidates[] = $unitPriceDeciCentsPerLiter;
        }

        if (preg_match_all('/(?P<decimal>\d+(?:[\.,]\s?\d{1,3}))/u', $context, $decimalMatches)) {
            foreach ($decimalMatches['decimal'] as $decimalCandidate) {
                $candidate = $decimalCandidate;

                $priceCandidate = $this->toPlausibleUnitPrice($candidate);
                if (null !== $priceCandidate) {
                    $priceCandidates[] = $priceCandidate;
                }
            }
        }

        if (preg_match_all('/\b(?:prix(?:\s*unit\.?)?|quantit[eé]?|carburant)\b(?P<segment>.{0,18})/ui', $context, $labelSegments)) {
            foreach ($labelSegments['segment'] as $segment) {
                if (preg_match('/(?P<minor>\d{3})\s*(?:eur|€)?/ui', $segment, $splitPrice)) {
                    $priceCandidate = $this->toPlausibleUnitPrice(sprintf('1.%s', $splitPrice['minor']));
                    if (null !== $priceCandidate) {
                        $priceCandidates[] = $priceCandidate;
                    }
                }
            }
        }

        if (preg_match_all('/\b(?P<minor>\d{3})\s*(?:eur|€)\b/ui', $context, $splitPriceMatches)) {
            foreach ($splitPriceMatches['minor'] as $minorCandidate) {
                $priceCandidate = $this->toPlausibleUnitPrice(sprintf('1.%s', (string) $minorCandidate));
                if (null !== $priceCandidate) {
                    $priceCandidates[] = $priceCandidate;
                }
            }
        }

        $quantityCandidates = array_values(array_unique($quantityCandidates));
        $priceCandidates = array_values(array_unique($priceCandidates));

        if ([] === $quantityCandidates) {
            foreach ($priceCandidates as $candidatePrice) {
                $inferredQuantity = $this->inferQuantityFromTotalAndUnitPrice($totalCents, $candidatePrice);
                if (null !== $inferredQuantity) {
                    $quantityCandidates[] = $inferredQuantity;
                }
            }
        }

        if ([] === $priceCandidates) {
            foreach ($quantityCandidates as $candidateQuantity) {
                if ($candidateQuantity <= 0) {
                    continue;
                }

                $inferredPrice = (int) round(($totalCents * 10000) / $candidateQuantity);
                if ($inferredPrice >= 500 && $inferredPrice <= 5000) {
                    $priceCandidates[] = $inferredPrice;
                }
            }
        }

        if ([] === $quantityCandidates || [] === $priceCandidates) {
            return [$quantityMilliLiters, $unitPriceDeciCentsPerLiter];
        }

        $bestPair = null;
        $bestDiff = PHP_INT_MAX;

        foreach ($quantityCandidates as $candidateQuantity) {
            foreach ($priceCandidates as $candidatePrice) {
                $estimatedTotalCents = (int) round(($candidateQuantity * $candidatePrice) / 10000);
                $diff = abs($estimatedTotalCents - $totalCents);
                if ($diff < $bestDiff) {
                    $bestDiff = $diff;
                    $bestPair = [$candidateQuantity, $candidatePrice];
                }
            }
        }

        if (null === $bestPair || $bestDiff > 150) {
            return [$quantityMilliLiters, $unitPriceDeciCentsPerLiter];
        }

        return [
            $quantityMilliLiters ?? $bestPair[0],
            $unitPriceDeciCentsPerLiter ?? $bestPair[1],
        ];
    }

    private function looksLikeLocationAliasCandidate(string $line): bool
    {
        if (preg_match('/\d{2}[\/\.-]\d{2}[\/\.-]\d{2,4}|\d{2}[:\-]\d{2}(?:[:\-]\d{2})?/u', $line)) {
            return false;
        }

        if (preg_match('/\b(carte|visa|debit|ticket|montant|tva|vat|terminal|contrat)\b/ui', $line)) {
            return false;
        }

        if (preg_match('/\b(hyper|centre|auto|station|relais)\b/ui', $line)) {
            return 1 === preg_match('/[A-Za-zÀ-ÿ].*[A-Za-zÀ-ÿ]/u', $line);
        }

        return 1 === preg_match('/\b(leclerc|petro|intermarche|total|energies)\b/ui', $line)
            && 1 === preg_match('/(?:\b[A-Za-zÀ-ÿ\'\-]+\b.*){3,}/u', $line);
    }

    private function scoreStationCandidate(string $candidate): int
    {
        $score = 0;

        if (preg_match('/\bpetro\s+est\b/ui', $candidate)) {
            $score += 12;
        }

        if (preg_match('/\b(petro|leclerc|intermarche|total|energies|relais|station)\b/ui', $candidate)) {
            $score += 8;
        }

        if (preg_match('/\b(transaction|acceptee?|autorise|client|ticket|debit|carte|pompe|numero)\b/ui', $candidate)) {
            $score -= 10;
        }

        if (preg_match('/\d{2}[\/\.-]\d{2}[\/\.-]\d{2,4}/', $candidate)) {
            $score -= 8;
        }

        if (preg_match('/\d/', $candidate)) {
            $score -= 2;
        }

        return $score;
    }

    private function normalizeStationName(string $candidate): string
    {
        if (preg_match('/\bpetro\s+est\b/ui', $candidate)) {
            return 'PETRO EST';
        }

        return trim($candidate);
    }

    /**
     * @param list<ParsedReceiptLineDraft> $lines
     */
    private function hasVatRateOnAnyFuelLine(array $lines): bool
    {
        foreach ($lines as $line) {
            if (null !== $line->vatRatePercent) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeCityCandidate(string $line): bool
    {
        if ($this->isNonAddressLine($line)) {
            return false;
        }

        if (preg_match('/\b(intermarche|total|petro|leclerc|station|relais|energies)\b/ui', $line)) {
            return false;
        }

        if (preg_match('/\b(bonjour|merci)\b/ui', $line)) {
            return false;
        }

        if (preg_match('/\d/u', $line)) {
            return false;
        }

        return 1 === preg_match('/^[A-ZÀ-Ÿ][A-ZÀ-Ÿ\'\- ]{2,100}$/u', mb_strtoupper($line));
    }

    /**
     * @param list<string> $lines
     */
    private function mergeNumericStreetPrefixBeforePostalCity(array $lines, int $postalCityIndex, string $streetBody): ?string
    {
        if ($postalCityIndex < 2 || !$this->looksLikeStreetHead($streetBody)) {
            return null;
        }

        $prefix = $lines[$postalCityIndex - 2];
        if (!preg_match('/^\d{1,4}\.?\s*$/u', $prefix)) {
            return null;
        }

        return trim(sprintf('%s %s', rtrim($prefix, ". \t"), $streetBody));
    }

    /**
     * @param list<string> $lines
     */
    private function mergeNumericStreetPrefixInStandaloneStreetSequence(array $lines, string $streetBody): ?string
    {
        if (!$this->looksLikeStreetHead($streetBody)) {
            return null;
        }

        $index = array_search($streetBody, $lines, true);
        if (!is_int($index) || $index < 1) {
            return null;
        }

        $prefix = $lines[$index - 1];
        if (!preg_match('/^\d{1,4}\.?\s*$/u', $prefix)) {
            return null;
        }

        $merged = trim(sprintf('%s %s', rtrim($prefix, ". \t"), $streetBody));
        if (isset($lines[$index + 1])) {
            $continuation = $lines[$index + 1];
            if (
                !$this->isNonAddressLine($continuation)
                && !preg_match('/\d/u', $continuation)
                && preg_match('/[A-Za-zÀ-ÿ]/u', $continuation)
            ) {
                $merged .= ' '.$continuation;
            }
        }

        return trim($merged);
    }

    private function extractSplitVatRate(string $segment): ?int
    {
        if (preg_match('/(?P<rate>\d{1,2})\s*[\.,]?\s*0{0,2}\s*%/u', $segment, $matches)) {
            $rate = (int) $matches['rate'];

            return $rate > 0 ? $rate : null;
        }

        return null;
    }

    private function extractSplitVatAmount(string $segment, int $rate, ?int $totalCents): ?int
    {
        if (preg_match_all('/(?<![\d\.,])(?P<whole>\d{1,3})\s+(?P<decimal>\d{2})(?!\d)/u', $segment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $candidate = sprintf('%s.%s', $match['whole'], $match['decimal']);
                if ($this->looksLikeVatRateValue($candidate, $rate)) {
                    continue;
                }

                $candidateCents = $this->decimalToCents($candidate);
                if (null === $candidateCents) {
                    continue;
                }

                if (null !== $totalCents && abs($candidateCents - $totalCents) <= 2) {
                    continue;
                }

                return $candidateCents;
            }
        }

        return null;
    }
}
