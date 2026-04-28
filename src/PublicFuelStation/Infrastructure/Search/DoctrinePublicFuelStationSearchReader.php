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

use App\PublicFuelStation\Application\Search\PublicFuelStationListItem;
use App\PublicFuelStation\Application\Search\PublicFuelStationSearchFilters;
use App\PublicFuelStation\Application\Search\PublicFuelStationSearchReader;
use App\PublicFuelStation\Application\Search\PublicFuelStationSearchResult;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DoctrinePublicFuelStationSearchReader implements PublicFuelStationSearchReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function search(PublicFuelStationSearchFilters $filters): PublicFuelStationSearchResult
    {
        [$whereSql, $parameters] = $this->buildWhere($filters);
        $totalCount = $this->readInt($this->connection->fetchOne('SELECT COUNT(*) FROM public_fuel_stations s '.$whereSql, $parameters));
        $limit = max(1, min($filters->limit, 300));

        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT source_id, latitude_micro_degrees, longitude_micro_degrees, address, postal_code, city, automate_24, services, fuels, source_updated_at
            FROM public_fuel_stations s
            {$whereSql}
            ORDER BY city ASC, postal_code ASC, address ASC
            LIMIT {$limit}
            SQL, $parameters);

        $items = [];
        foreach ($rows as $row) {
            $item = $this->mapRow($row);
            if ($item instanceof PublicFuelStationListItem) {
                $items[] = $item;
            }
        }

        return new PublicFuelStationSearchResult($items, $totalCount, $limit);
    }

    /**
     * @return array{0:string, 1:array<string, string>}
     */
    private function buildWhere(PublicFuelStationSearchFilters $filters): array
    {
        $clauses = ['s.latitude_micro_degrees IS NOT NULL', 's.longitude_micro_degrees IS NOT NULL'];
        $parameters = [];

        if (null !== $filters->query && '' !== trim($filters->query)) {
            $parameters['query'] = '%'.mb_strtolower(trim($filters->query)).'%';
            $clauses[] = '(LOWER(s.city) LIKE :query OR LOWER(s.postal_code) LIKE :query OR LOWER(s.address) LIKE :query)';
        }

        if (null !== $filters->fuelType) {
            $fuelKey = $this->connection->quote($filters->fuelType->value);
            if ($filters->availableOnly) {
                $clauses[] = sprintf("(s.fuels -> %s ->> 'available') = 'true'", $fuelKey);
            }
            $clauses[] = sprintf("(s.fuels -> %s ->> 'priceMilliEurosPerLiter') IS NOT NULL", $fuelKey);
        }

        return [' WHERE '.implode(' AND ', $clauses), $parameters];
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): ?PublicFuelStationListItem
    {
        $latitudeMicroDegrees = $this->readIntOrNull($row['latitude_micro_degrees'] ?? null);
        $longitudeMicroDegrees = $this->readIntOrNull($row['longitude_micro_degrees'] ?? null);
        if (null === $latitudeMicroDegrees || null === $longitudeMicroDegrees) {
            return null;
        }

        return new PublicFuelStationListItem(
            $this->readString($row['source_id'] ?? null),
            $latitudeMicroDegrees / 1_000_000,
            $longitudeMicroDegrees / 1_000_000,
            $this->readString($row['address'] ?? null),
            $this->readString($row['postal_code'] ?? null),
            $this->readString($row['city'] ?? null),
            $this->readBool($row['automate_24'] ?? null),
            $this->readStringListJson($row['services'] ?? null),
            $this->readFuelJson($row['fuels'] ?? null),
            $this->readDateTime($row['source_updated_at'] ?? null),
        );
    }

    /** @return list<string> */
    private function readStringListJson(mixed $value): array
    {
        if (!is_string($value) || '' === trim($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = [];
        foreach ($decoded as $item) {
            if (is_string($item) && '' !== trim($item)) {
                $items[] = trim($item);
            }
        }

        return $items;
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

    private function readBool(mixed $value): bool
    {
        return true === $value || '1' === $value || 1 === $value || 'true' === $value;
    }

    private function readInt(mixed $value): int
    {
        return $this->readIntOrNull($value) ?? 0;
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

    private function readDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        $date = date_create_immutable($value);

        return $date instanceof DateTimeImmutable ? $date : null;
    }
}
