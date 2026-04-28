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

namespace App\PublicFuelStation\Infrastructure\Persistence\Doctrine\Repository;

use App\PublicFuelStation\Application\Import\ParsedPublicFuelStation;
use App\PublicFuelStation\Application\Repository\PublicFuelStationRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use JsonException;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePublicFuelStationRepository implements PublicFuelStationRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @throws JsonException
     */
    public function upsert(ParsedPublicFuelStation $station): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $sourceUpdatedAt = $station->sourceUpdatedAt?->setTimezone(new DateTimeZone('UTC'));
        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO public_fuel_stations (
                id,
                source_id,
                latitude_micro_degrees,
                longitude_micro_degrees,
                address,
                postal_code,
                city,
                population_kind,
                department,
                department_code,
                region,
                region_code,
                automate_24,
                services,
                fuels,
                source_updated_at,
                imported_at
            ) VALUES (
                :id,
                :sourceId,
                :latitudeMicroDegrees,
                :longitudeMicroDegrees,
                :address,
                :postalCode,
                :city,
                :populationKind,
                :department,
                :departmentCode,
                :region,
                :regionCode,
                CAST(:automate24 AS BOOLEAN),
                CAST(:services AS JSON),
                CAST(:fuels AS JSON),
                :sourceUpdatedAt,
                :importedAt
            )
            ON CONFLICT (source_id) DO UPDATE SET
                latitude_micro_degrees = EXCLUDED.latitude_micro_degrees,
                longitude_micro_degrees = EXCLUDED.longitude_micro_degrees,
                address = EXCLUDED.address,
                postal_code = EXCLUDED.postal_code,
                city = EXCLUDED.city,
                population_kind = EXCLUDED.population_kind,
                department = EXCLUDED.department,
                department_code = EXCLUDED.department_code,
                region = EXCLUDED.region,
                region_code = EXCLUDED.region_code,
                automate_24 = EXCLUDED.automate_24,
                services = EXCLUDED.services,
                fuels = EXCLUDED.fuels,
                source_updated_at = EXCLUDED.source_updated_at,
                imported_at = EXCLUDED.imported_at
            SQL, [
            'id' => Uuid::v7()->toRfc4122(),
            'sourceId' => $station->sourceId,
            'latitudeMicroDegrees' => $station->latitudeMicroDegrees,
            'longitudeMicroDegrees' => $station->longitudeMicroDegrees,
            'address' => $station->address,
            'postalCode' => $station->postalCode,
            'city' => $station->city,
            'populationKind' => $station->populationKind,
            'department' => $station->department,
            'departmentCode' => $station->departmentCode,
            'region' => $station->region,
            'regionCode' => $station->regionCode,
            'automate24' => $station->automate24 ? 'true' : 'false',
            'services' => json_encode($station->services, JSON_THROW_ON_ERROR),
            'fuels' => json_encode($station->fuels, JSON_THROW_ON_ERROR),
            'sourceUpdatedAt' => $sourceUpdatedAt?->format('Y-m-d H:i:s'),
            'importedAt' => $now->format('Y-m-d H:i:s'),
        ]);
    }

    public function countAll(): int
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM public_fuel_stations');

        return is_numeric($count) ? (int) $count : 0;
    }
}
