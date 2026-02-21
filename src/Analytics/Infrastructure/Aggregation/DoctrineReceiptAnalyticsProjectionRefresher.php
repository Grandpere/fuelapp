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

namespace App\Analytics\Infrastructure\Aggregation;

use App\Analytics\Application\Aggregation\ReceiptAnalyticsProjectionRefresher;
use App\Analytics\Application\Aggregation\ReceiptAnalyticsRefreshReport;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class DoctrineReceiptAnalyticsProjectionRefresher implements ReceiptAnalyticsProjectionRefresher
{
    private const PROJECTION = 'receipt_daily_fuel_kpi_v1';

    public function __construct(private Connection $connection)
    {
    }

    public function refresh(): ReceiptAnalyticsRefreshReport
    {
        $now = new DateTimeImmutable();

        try {
            $rowsMaterialized = $this->connection->transactional(function (Connection $connection): int {
                $connection->executeStatement('DELETE FROM analytics_daily_fuel_kpis');

                return (int) $connection->executeStatement(
                    <<<'SQL'
                            INSERT INTO analytics_daily_fuel_kpis (
                                owner_id,
                                day,
                                vehicle_id,
                                station_id,
                                fuel_type,
                                receipt_count,
                                line_count,
                                total_cost_cents,
                                total_quantity_milli_liters,
                                updated_at
                            )
                            SELECT
                                r.owner_id,
                                DATE(r.issued_at) AS day,
                                r.vehicle_id,
                                r.station_id,
                                rl.fuel_type,
                                COUNT(DISTINCT r.id)::int AS receipt_count,
                                COUNT(rl.id)::int AS line_count,
                                COALESCE(SUM(ROUND((rl.unit_price_deci_cents_per_liter::numeric * rl.quantity_milli_liters::numeric) / 10000)), 0)::bigint AS total_cost_cents,
                                COALESCE(SUM(rl.quantity_milli_liters), 0)::bigint AS total_quantity_milli_liters,
                                NOW()::timestamp(0) without time zone AS updated_at
                            FROM receipts r
                            INNER JOIN receipt_lines rl ON rl.receipt_id = r.id
                            WHERE r.owner_id IS NOT NULL
                            GROUP BY r.owner_id, DATE(r.issued_at), r.vehicle_id, r.station_id, rl.fuel_type
                        SQL,
                );
            });
        } catch (Throwable $exception) {
            $this->saveFailedState($now, $exception->getMessage());

            throw $exception;
        }

        $source = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS receipt_count, MAX(issued_at) AS max_issued_at FROM receipts WHERE owner_id IS NOT NULL',
        );

        $sourceReceiptCount = $this->toInt($source['receipt_count'] ?? 0);
        $sourceMaxIssuedAt = $this->toDateTimeImmutable($source['max_issued_at'] ?? null);

        $this->connection->executeStatement(
            <<<'SQL'
                    INSERT INTO analytics_projection_states (
                        projection,
                        last_refreshed_at,
                        source_max_issued_at,
                        source_receipt_count,
                        rows_materialized,
                        status,
                        last_error,
                        updated_at
                    )
                    VALUES (
                        :projection,
                        :lastRefreshedAt,
                        :sourceMaxIssuedAt,
                        :sourceReceiptCount,
                        :rowsMaterialized,
                        :status,
                        NULL,
                        :updatedAt
                    )
                    ON CONFLICT (projection) DO UPDATE
                    SET
                        last_refreshed_at = EXCLUDED.last_refreshed_at,
                        source_max_issued_at = EXCLUDED.source_max_issued_at,
                        source_receipt_count = EXCLUDED.source_receipt_count,
                        rows_materialized = EXCLUDED.rows_materialized,
                        status = EXCLUDED.status,
                        last_error = NULL,
                        updated_at = EXCLUDED.updated_at
                SQL,
            [
                'projection' => self::PROJECTION,
                'lastRefreshedAt' => $now,
                'sourceMaxIssuedAt' => $sourceMaxIssuedAt,
                'sourceReceiptCount' => $sourceReceiptCount,
                'rowsMaterialized' => $rowsMaterialized,
                'status' => 'fresh',
                'updatedAt' => $now,
            ],
            [
                'lastRefreshedAt' => 'datetime_immutable',
                'sourceMaxIssuedAt' => 'datetime_immutable',
                'updatedAt' => 'datetime_immutable',
            ],
        );

        return new ReceiptAnalyticsRefreshReport($rowsMaterialized, $sourceReceiptCount, $sourceMaxIssuedAt, $now);
    }

    private function saveFailedState(DateTimeImmutable $now, string $message): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                    INSERT INTO analytics_projection_states (
                        projection,
                        last_refreshed_at,
                        source_max_issued_at,
                        source_receipt_count,
                        rows_materialized,
                        status,
                        last_error,
                        updated_at
                    )
                    VALUES (
                        :projection,
                        NULL,
                        NULL,
                        0,
                        0,
                        :status,
                        :lastError,
                        :updatedAt
                    )
                    ON CONFLICT (projection) DO UPDATE
                    SET
                        status = EXCLUDED.status,
                        last_error = EXCLUDED.last_error,
                        updated_at = EXCLUDED.updated_at
                SQL,
            [
                'projection' => self::PROJECTION,
                'status' => 'failed',
                'lastError' => mb_substr($message, 0, 4000),
                'updatedAt' => $now,
            ],
            [
                'updatedAt' => 'datetime_immutable',
            ],
        );
    }

    private function toDateTimeImmutable(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (is_string($value) && '' !== trim($value)) {
            return new DateTimeImmutable($value);
        }

        return null;
    }

    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return 0;
    }
}
