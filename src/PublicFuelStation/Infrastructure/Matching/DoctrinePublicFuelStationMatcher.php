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

    /** @return list<PublicFuelStationMatchCandidate> */
    private function findByCoordinates(VisitedStationPublicMatchQuery $query): array
    {
        $limit = max(1, min($query->limit, 5));
        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT source_id, address, postal_code, city, fuels,
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
            SELECT source_id, address, postal_code, city, fuels, NULL AS distance_meters
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
     * @param list<array<string, mixed>> $rows
     *
     * @return list<PublicFuelStationMatchCandidate>
     */
    private function mapRows(array $rows): array
    {
        $candidates = [];
        foreach ($rows as $row) {
            $distanceMeters = $this->readIntOrNull($row['distance_meters'] ?? null);
            $candidates[] = new PublicFuelStationMatchCandidate(
                $this->readString($row['source_id'] ?? null),
                $this->readString($row['address'] ?? null),
                $this->readString($row['postal_code'] ?? null),
                $this->readString($row['city'] ?? null),
                $distanceMeters,
                $this->confidence($distanceMeters),
                $this->readFuelJson($row['fuels'] ?? null),
            );
        }

        return $candidates;
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
