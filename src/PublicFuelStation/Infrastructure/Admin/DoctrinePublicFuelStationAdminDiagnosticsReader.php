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

namespace App\PublicFuelStation\Infrastructure\Admin;

use App\PublicFuelStation\Application\Admin\PublicFuelStationAdminDiagnosticsReader;
use App\PublicFuelStation\Application\Admin\PublicFuelStationSyncDiagnostics;
use App\PublicFuelStation\Application\Admin\PublicFuelStationSyncRunSummary;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DoctrinePublicFuelStationAdminDiagnosticsReader implements PublicFuelStationAdminDiagnosticsReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function read(): PublicFuelStationSyncDiagnostics
    {
        $stationCount = $this->readInt($this->connection->fetchOne('SELECT COUNT(*) FROM public_fuel_stations'));
        $latestSourceUpdatedAt = $this->readDateTime($this->connection->fetchOne('SELECT MAX(source_updated_at) FROM public_fuel_stations'));
        $latestImportedAt = $this->readDateTime($this->connection->fetchOne('SELECT MAX(imported_at) FROM public_fuel_stations'));
        $recentRuns = $this->readRecentRuns();

        return new PublicFuelStationSyncDiagnostics(
            $stationCount,
            $latestSourceUpdatedAt,
            $latestImportedAt,
            $recentRuns[0] ?? null,
            $recentRuns,
        );
    }

    /** @return list<PublicFuelStationSyncRunSummary> */
    private function readRecentRuns(): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT id, source_url, status, started_at, completed_at, processed_count, upserted_count, rejected_count, error_message
            FROM public_fuel_station_sync_runs
            ORDER BY started_at DESC
            LIMIT 10
            SQL);

        $runs = [];
        foreach ($rows as $row) {
            $startedAt = $this->readDateTime($row['started_at'] ?? null);
            if (!$startedAt instanceof DateTimeImmutable) {
                continue;
            }

            $runs[] = new PublicFuelStationSyncRunSummary(
                $this->readString($row['id'] ?? null),
                $this->readString($row['source_url'] ?? null),
                $this->readString($row['status'] ?? null),
                $startedAt,
                $this->readDateTime($row['completed_at'] ?? null),
                $this->readInt($row['processed_count'] ?? null),
                $this->readInt($row['upserted_count'] ?? null),
                $this->readInt($row['rejected_count'] ?? null),
                null === ($row['error_message'] ?? null) ? null : $this->readString($row['error_message']),
            );
        }

        return $runs;
    }

    private function readInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
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
