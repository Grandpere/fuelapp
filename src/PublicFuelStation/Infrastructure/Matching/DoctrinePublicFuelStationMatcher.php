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

namespace App\PublicFuelStation\Infrastructure\Matching;

use App\PublicFuelStation\Application\Matching\PublicFuelStationMatchCandidate;
use App\PublicFuelStation\Application\Matching\PublicFuelStationMatcher;
use App\PublicFuelStation\Application\Matching\VisitedStationPublicMatchQuery;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DoctrinePublicFuelStationMatcher implements PublicFuelStationMatcher
{
    public function __construct(private Connection $connection)
    {
    }

    public function findCandidates(VisitedStationPublicMatchQuery $query): array
    {
        if (null !== $query->latitudeMicroDegrees && null !== $query->longitudeMicroDegrees) {
            return $this->findByCoordinates($query);
        }

        return $this->findByAddressContext($query);
    }

    public function findBestCandidates(array $queries): array
    {
        $coordinateQueries = [];
        $fallbackQueries = [];

        foreach ($queries as $key => $query) {
            if (null !== $query->latitudeMicroDegrees && null !== $query->longitudeMicroDegrees) {
                $coordinateQueries[$key] = $query;

                continue;
            }

            $fallbackQueries[$key] = $query;
        }

        $matches = $this->findBestByCoordinates($coordinateQueries);

        foreach ($fallbackQueries as $key => $query) {
            $match = $this->findCandidates($query)[0] ?? null;
            if ($match instanceof PublicFuelStationMatchCandidate) {
                $matches[$key] = $match;
            }
        }

        return $matches;
    }

    /** @return list<PublicFuelStationMatchCandidate> */
    private function findByCoordinates(VisitedStationPublicMatchQuery $query): array
    {
        $limit = max(1, min($query->limit, 5));
        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT source_id, address, postal_code, city, latitude_micro_degrees, longitude_micro_degrees, fuels,
                SQRT(
                    POWER((latitude_micro_degrees - :latitude) * 0.11132, 2)
                    + POWER((longitude_micro_degrees - :longitude) * 0.11132 * COS(RADIANS(:latitude / 1000000.0)), 2)
                ) AS distance_meters
            FROM public_fuel_stations
            WHERE latitude_micro_degrees IS NOT NULL
                AND longitude_micro_degrees IS NOT NULL
                AND ABS(latitude_micro_degrees - :latitude) <= 15000
                AND ABS(longitude_micro_degrees - :longitude) <= 20000
            ORDER BY distance_meters ASC
            LIMIT {$limit}
            SQL, [
            'latitude' => $query->latitudeMicroDegrees,
            'longitude' => $query->longitudeMicroDegrees,
        ]);

        return $this->mapRows($rows);
    }

    /** @return list<PublicFuelStationMatchCandidate> */
    private function findByAddressContext(VisitedStationPublicMatchQuery $query): array
    {
        $limit = max(1, min($query->limit, 5));
        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT source_id, address, postal_code, city, latitude_micro_degrees, longitude_micro_degrees, fuels, NULL AS distance_meters
            FROM public_fuel_stations
            WHERE postal_code = :postalCode
                AND LOWER(city) = :city
            ORDER BY CASE WHEN LOWER(address) LIKE :streetName THEN 0 ELSE 1 END, address ASC
            LIMIT {$limit}
            SQL, [
            'postalCode' => $query->postalCode,
            'city' => mb_strtolower($query->city),
            'streetName' => '%'.mb_strtolower($query->streetName).'%',
        ]);

        return $this->mapRows($rows);
    }

    /**
     * @param array<string, VisitedStationPublicMatchQuery> $queries
     *
     * @return array<string, PublicFuelStationMatchCandidate>
     */
    private function findBestByCoordinates(array $queries): array
    {
        if ([] === $queries) {
            return [];
        }

        $values = [];
        $params = [];
        $types = [];
        $index = 0;

        foreach ($queries as $key => $query) {
            $values[] = "(:key_{$index}, CAST(:latitude_{$index} AS INTEGER), CAST(:longitude_{$index} AS INTEGER))";
            $params["key_{$index}"] = $key;
            $params["latitude_{$index}"] = $query->latitudeMicroDegrees;
            $params["longitude_{$index}"] = $query->longitudeMicroDegrees;
            $types["key_{$index}"] = ParameterType::STRING;
            $types["latitude_{$index}"] = ParameterType::INTEGER;
            $types["longitude_{$index}"] = ParameterType::INTEGER;
            ++$index;
        }

        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                <<<'SQL'
                        WITH visited_points (query_key, latitude_micro_degrees, longitude_micro_degrees) AS (
                            VALUES %s
                        ),
                        ranked_matches AS (
                            SELECT
                                visited_points.query_key,
                                source_id,
                                address,
                                postal_code,
                                city,
                                public_fuel_stations.latitude_micro_degrees,
                                public_fuel_stations.longitude_micro_degrees,
                                fuels,
                                SQRT(
                                    POWER((public_fuel_stations.latitude_micro_degrees - visited_points.latitude_micro_degrees) * 0.11132, 2)
                                    + POWER((public_fuel_stations.longitude_micro_degrees - visited_points.longitude_micro_degrees) * 0.11132 * COS(RADIANS(visited_points.latitude_micro_degrees / 1000000.0)), 2)
                                ) AS distance_meters,
                                ROW_NUMBER() OVER (
                                    PARTITION BY visited_points.query_key
                                    ORDER BY
                                        SQRT(
                                            POWER((public_fuel_stations.latitude_micro_degrees - visited_points.latitude_micro_degrees) * 0.11132, 2)
                                            + POWER((public_fuel_stations.longitude_micro_degrees - visited_points.longitude_micro_degrees) * 0.11132 * COS(RADIANS(visited_points.latitude_micro_degrees / 1000000.0)), 2)
                                        ) ASC
                                ) AS match_rank
                            FROM visited_points
                            JOIN public_fuel_stations
                                ON public_fuel_stations.latitude_micro_degrees IS NOT NULL
                                AND public_fuel_stations.longitude_micro_degrees IS NOT NULL
                                AND ABS(public_fuel_stations.latitude_micro_degrees - visited_points.latitude_micro_degrees) <= 15000
                                AND ABS(public_fuel_stations.longitude_micro_degrees - visited_points.longitude_micro_degrees) <= 20000
                        )
                        SELECT query_key, source_id, address, postal_code, city, latitude_micro_degrees, longitude_micro_degrees, fuels, distance_meters
                        FROM ranked_matches
                        WHERE match_rank = 1
                    SQL,
                implode(', ', $values),
            ),
            $params,
            $types,
        );

        $matches = [];
        foreach ($rows as $row) {
            $queryKey = $this->readString($row['query_key'] ?? null);
            if ('' === $queryKey) {
                continue;
            }

            $matches[$queryKey] = $this->mapRow($row);
        }

        /** @var array<string, PublicFuelStationMatchCandidate> $filtered */
        $filtered = array_filter($matches, static fn (mixed $match): bool => $match instanceof PublicFuelStationMatchCandidate);

        return $filtered;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<PublicFuelStationMatchCandidate>
     */
    private function mapRows(array $rows): array
    {
        $candidates = [];
        foreach ($rows as $row) {
            $candidate = $this->mapRow($row);
            if ($candidate instanceof PublicFuelStationMatchCandidate) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): ?PublicFuelStationMatchCandidate
    {
        $sourceId = $this->readString($row['source_id'] ?? null);
        if ('' === $sourceId) {
            return null;
        }

        $distanceMeters = $this->readIntOrNull($row['distance_meters'] ?? null);

        return new PublicFuelStationMatchCandidate(
            $sourceId,
            $this->readString($row['address'] ?? null),
            $this->readString($row['postal_code'] ?? null),
            $this->readString($row['city'] ?? null),
            $this->readIntOrNull($row['latitude_micro_degrees'] ?? null),
            $this->readIntOrNull($row['longitude_micro_degrees'] ?? null),
            $distanceMeters,
            $this->confidence($distanceMeters),
            $this->readFuelJson($row['fuels'] ?? null),
        );
    }

    private function confidence(?int $distanceMeters): string
    {
        if (null === $distanceMeters) {
            return 'address context';
        }

        if ($distanceMeters <= 120) {
            return 'high';
        }

        if ($distanceMeters <= 500) {
            return 'medium';
        }

        return 'low';
    }

    /** @return array<string, array<string, bool|int|string|null>> */
    private function readFuelJson(mixed $value): array
    {
        if (!is_string($value) || '' === trim($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        $fuels = [];
        foreach ($decoded as $fuel => $snapshot) {
            if (!is_string($fuel) || !is_array($snapshot)) {
                continue;
            }

            $fuels[$fuel] = [
                'available' => true === ($snapshot['available'] ?? false),
                'priceMilliEurosPerLiter' => $this->readIntOrNull($snapshot['priceMilliEurosPerLiter'] ?? null),
                'priceUpdatedAt' => is_string($snapshot['priceUpdatedAt'] ?? null) ? $snapshot['priceUpdatedAt'] : null,
                'ruptureType' => is_string($snapshot['ruptureType'] ?? null) ? $snapshot['ruptureType'] : null,
                'ruptureStartedAt' => is_string($snapshot['ruptureStartedAt'] ?? null) ? $snapshot['ruptureStartedAt'] : null,
            ];
        }

        return $fuels;
    }

    private function readIntOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        return null;
    }

    private function readString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}
