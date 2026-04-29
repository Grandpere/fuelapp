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

namespace App\PublicFuelStation\Infrastructure\Search;

use App\PublicFuelStation\Application\Search\PublicFuelStationSuggestion;
use App\PublicFuelStation\Application\Search\PublicFuelStationSuggestionReader;
use App\Station\Application\Suggestion\StationSuggestionQuery;
use Doctrine\DBAL\Connection;

final readonly class DoctrinePublicFuelStationSuggestionReader implements PublicFuelStationSuggestionReader
{
    private const STOP_WORDS = [
        'a',
        'au',
        'aux',
        'av',
        'avenue',
        'bd',
        'boulevard',
        'c',
        'cc',
        'centre',
        'chemin',
        'commercial',
        'de',
        'des',
        'dit',
        'du',
        'et',
        'la',
        'le',
        'les',
        'lieu',
        'route',
        'rue',
        'sas',
        'sa',
        'sarl',
        'service',
        'services',
        'station',
        'ste',
        'sur',
        'total',
    ];

    public function __construct(private Connection $connection)
    {
    }

    public function search(StationSuggestionQuery $query, int $limit): array
    {
        $terms = $this->terms($query);
        if ([] === $terms) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder()
            ->select('source_id', 'address', 'postal_code', 'city', 'latitude_micro_degrees', 'longitude_micro_degrees')
            ->from('public_fuel_stations')
            ->where('latitude_micro_degrees IS NOT NULL')
            ->andWhere('longitude_micro_degrees IS NOT NULL')
            ->orderBy('city', 'ASC')
            ->addOrderBy('postal_code', 'ASC')
            ->addOrderBy('address', 'ASC')
            ->setMaxResults(max(50, $limit * 20));

        $conditions = [];
        foreach ($terms as $index => $term) {
            $parameter = 'term'.$index;
            $conditions[] = sprintf('(LOWER(address) LIKE :%1$s OR LOWER(city) LIKE :%1$s OR postal_code LIKE :%1$s)', $parameter);
            $qb->setParameter($parameter, '%'.$term.'%');
        }

        $qb->andWhere(implode(' OR ', $conditions));

        $scoredResults = [];
        foreach ($qb->executeQuery()->fetchAllAssociative() as $row) {
            $candidate = $this->mapRow($row);
            if (!$candidate instanceof PublicFuelStationSuggestion) {
                continue;
            }

            $score = $this->scoreCandidate($candidate, $query, $terms);
            if ($score <= 0) {
                continue;
            }

            $scoredResults[] = [
                'candidate' => $candidate,
                'score' => $score,
            ];
        }

        usort(
            $scoredResults,
            static fn (array $left, array $right): int => [$right['score'], $left['candidate']->city, $left['candidate']->postalCode, $left['candidate']->name]
                <=> [$left['score'], $right['candidate']->city, $right['candidate']->postalCode, $right['candidate']->name],
        );

        return array_map(
            static fn (array $row): PublicFuelStationSuggestion => $row['candidate'],
            array_slice($scoredResults, 0, max(1, $limit)),
        );
    }

    public function getBySourceId(string $sourceId): ?PublicFuelStationSuggestion
    {
        $row = $this->connection->fetchAssociative(
            'SELECT source_id, address, postal_code, city, latitude_micro_degrees, longitude_micro_degrees FROM public_fuel_stations WHERE source_id = :sourceId LIMIT 1',
            ['sourceId' => $sourceId],
        );

        if (!is_array($row)) {
            return null;
        }

        return $this->mapRow($row);
    }

    /**
     * @return list<string>
     */
    private function terms(StationSuggestionQuery $query): array
    {
        $parts = array_filter([
            $query->freeText,
            $query->name,
            $query->streetName,
            $query->postalCode,
            $query->city,
        ], static fn (?string $value): bool => null !== $value && '' !== trim($value));

        if ([] === $parts) {
            return [];
        }

        $merged = mb_strtolower(implode(' ', array_map(static fn (string $value): string => trim($value), $parts)));

        /** @var list<string> $terms */
        $terms = array_values(array_filter(preg_split('/[^\pL\pN]+/u', $merged) ?: []));

        return array_values(array_unique(array_filter(
            $terms,
            fn (string $term): bool => $this->isSignificantTerm($term),
        )));
    }

    /**
     * @param list<string> $terms
     */
    private function scoreCandidate(PublicFuelStationSuggestion $candidate, StationSuggestionQuery $query, array $terms): int
    {
        $score = 0;
        $matchedTerms = 0;
        $freeText = mb_strtolower(trim((string) ($query->freeText ?? '')));
        $haystack = mb_strtolower(implode(' ', [
            $candidate->name,
            $candidate->streetName,
            $candidate->postalCode,
            $candidate->city,
        ]));

        foreach ($terms as $term) {
            if (str_contains($haystack, $term)) {
                ++$matchedTerms;
                ++$score;
            }
        }

        $candidatePostal = mb_strtolower(trim($candidate->postalCode));
        $candidateCity = mb_strtolower(trim($candidate->city));
        $candidateName = mb_strtolower(trim($candidate->name));
        $candidateStreet = mb_strtolower(trim($candidate->streetName));

        $queryPostal = $this->normalizeScalarTerm($query->postalCode);
        $queryCity = $this->normalizeScalarTerm($query->city);
        $queryName = $this->normalizeScalarTerm($query->name);
        $queryStreet = $this->normalizeScalarTerm($query->streetName);

        if ('' !== $queryPostal && $candidatePostal === $queryPostal) {
            $score += 6;
        }

        if ('' !== $queryCity && $candidateCity === $queryCity) {
            $score += 5;
        }

        if ('' !== $queryName && str_contains($candidateName, $queryName)) {
            $score += 4;
        }

        if ('' !== $queryStreet && str_contains($candidateStreet, $queryStreet)) {
            $score += 4;
        }

        if ('' !== $freeText) {
            if (str_contains($candidateName, $freeText)) {
                $score += 5;
            } elseif (str_contains($candidateCity, $freeText) || str_contains($candidateStreet, $freeText)) {
                $score += 3;
            }
        }

        if (
            '' !== $queryPostal
            && $candidatePostal !== $queryPostal
            && '' !== $queryCity
            && $candidateCity !== $queryCity
            && $matchedTerms < 3
        ) {
            return 0;
        }

        if ($matchedTerms >= 2) {
            return $score;
        }

        if ('' !== $freeText && (str_contains($candidateName, $freeText) || str_contains($candidateCity, $freeText))) {
            return $score;
        }

        return $score >= 5 ? $score : 0;
    }

    private function normalizeScalarTerm(?string $value): string
    {
        if (null === $value) {
            return '';
        }

        return mb_strtolower(trim($value));
    }

    private function isSignificantTerm(string $term): bool
    {
        $normalized = trim($term);
        if ('' === $normalized) {
            return false;
        }

        if (in_array($normalized, self::STOP_WORDS, true)) {
            return false;
        }

        if (ctype_digit($normalized)) {
            return strlen($normalized) >= 4;
        }

        return mb_strlen($normalized) >= 3;
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): ?PublicFuelStationSuggestion
    {
        $sourceId = $this->readString($row['source_id'] ?? null);
        $address = $this->readString($row['address'] ?? null);
        if ('' === $sourceId || '' === $address) {
            return null;
        }

        return new PublicFuelStationSuggestion(
            $sourceId,
            $address,
            $address,
            $this->readString($row['postal_code'] ?? null),
            $this->readString($row['city'] ?? null),
            $this->readIntOrNull($row['latitude_micro_degrees'] ?? null),
            $this->readIntOrNull($row['longitude_micro_degrees'] ?? null),
        );
    }

    private function readString(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    private function readIntOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
