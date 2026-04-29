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

namespace App\PublicFuelStation\Infrastructure\Nearby;

use App\PublicFuelStation\Application\Nearby\NearbyPublicFuelStationPoint;
use App\PublicFuelStation\Application\Nearby\PublicFuelStationNearbyReader;
use App\PublicFuelStation\Domain\Enum\PublicFuelType;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DoctrinePublicFuelStationNearbyReader implements PublicFuelStationNearbyReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function findNearby(array $queries, array $excludedSourceIds = [], int $limitPerQuery = 2, int $maxDistanceMeters = 2500): array
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

        $limitPerQuery = max(1, min($limitPerQuery, 4));
        $maxDistanceMeters = max(200, min($maxDistanceMeters, 5_000));
        $latitudeDeltaMicroDegrees = (int) ceil($maxDistanceMeters / 0.11132);

        $excludedSql = '';
        if ([] !== $excludedSourceIds) {
            $excludedSql = 'AND public_fuel_stations.source_id NOT IN (:excluded_source_ids)';
            $params['excluded_source_ids'] = array_values(array_unique($excludedSourceIds));
            $types['excluded_source_ids'] = ArrayParameterType::STRING;
        }

        $params['latitude_delta_micro_degrees'] = $latitudeDeltaMicroDegrees;
        $params['max_distance_meters'] = $maxDistanceMeters;
        $types['latitude_delta_micro_degrees'] = ParameterType::INTEGER;
        $types['max_distance_meters'] = ParameterType::INTEGER;

        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                <<<'SQL'
                    WITH visited_points (query_key, latitude_micro_degrees, longitude_micro_degrees) AS (
                        VALUES %s
                    ),
                    ranked_points AS (
                        SELECT
                            visited_points.query_key,
                            public_fuel_stations.source_id,
                            public_fuel_stations.address,
                            public_fuel_stations.postal_code,
                            public_fuel_stations.city,
                            public_fuel_stations.latitude_micro_degrees,
                            public_fuel_stations.longitude_micro_degrees,
                            public_fuel_stations.fuels,
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
                            ) AS point_rank
                        FROM visited_points
                        JOIN public_fuel_stations
                            ON public_fuel_stations.latitude_micro_degrees IS NOT NULL
                            AND public_fuel_stations.longitude_micro_degrees IS NOT NULL
                            AND ABS(public_fuel_stations.latitude_micro_degrees - visited_points.latitude_micro_degrees) <= :latitude_delta_micro_degrees
                            AND ABS(public_fuel_stations.longitude_micro_degrees - visited_points.longitude_micro_degrees) <= CEIL(
                                :max_distance_meters / (
                                    0.11132 * GREATEST(
                                        0.2,
                                        ABS(COS(RADIANS(visited_points.latitude_micro_degrees / 1000000.0)))
                                    )
                                )
                            )
                            %s
                    )
                    SELECT query_key, source_id, address, postal_code, city, latitude_micro_degrees, longitude_micro_degrees, fuels, distance_meters
                    FROM ranked_points
                    WHERE point_rank <= %d
                        AND distance_meters <= %d
                    ORDER BY query_key ASC, distance_meters ASC
                    SQL,
                implode(', ', $values),
                $excludedSql,
                $limitPerQuery,
                $maxDistanceMeters,
            ),
            $params,
            $types,
        );

        $points = [];
        foreach ($rows as $row) {
            $queryKey = $this->readString($row['query_key'] ?? null);
            $point = $this->mapRow($row);
            if ('' === $queryKey || null === $point) {
                continue;
            }

            $points[$queryKey] ??= [];
            $points[$queryKey][] = $point;
        }

        return $points;
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): ?NearbyPublicFuelStationPoint
    {
        $sourceId = $this->readString($row['source_id'] ?? null);
        $latitudeMicroDegrees = $this->readIntOrNull($row['latitude_micro_degrees'] ?? null);
        $longitudeMicroDegrees = $this->readIntOrNull($row['longitude_micro_degrees'] ?? null);
        $distanceMeters = $this->readIntOrNull($row['distance_meters'] ?? null);

        if ('' === $sourceId || null === $latitudeMicroDegrees || null === $longitudeMicroDegrees || null === $distanceMeters) {
            return null;
        }

        return new NearbyPublicFuelStationPoint(
            $sourceId,
            $this->readString($row['address'] ?? null),
            $this->readString($row['postal_code'] ?? null),
            $this->readString($row['city'] ?? null),
            $latitudeMicroDegrees / 1_000_000.0,
            $longitudeMicroDegrees / 1_000_000.0,
            $distanceMeters,
            $this->availableFuelLabels($row['fuels'] ?? null),
        );
    }

    /** @return list<string> */
    private function availableFuelLabels(mixed $value): array
    {
        if (!is_string($value) || '' === trim($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        $labels = [];
        foreach ($decoded as $fuel => $snapshot) {
            if (!is_string($fuel) || !is_array($snapshot) || true !== ($snapshot['available'] ?? false)) {
                continue;
            }

            $labels[] = PublicFuelType::tryFrom($fuel)?->sourceLabel() ?? strtoupper($fuel);
        }

        sort($labels);

        return $labels;
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
