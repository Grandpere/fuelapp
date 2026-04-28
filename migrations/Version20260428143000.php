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

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add public fuel station directory cache tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE public_fuel_stations (id UUID NOT NULL, source_id VARCHAR(40) NOT NULL, latitude_micro_degrees INT DEFAULT NULL, longitude_micro_degrees INT DEFAULT NULL, address VARCHAR(255) NOT NULL, postal_code VARCHAR(20) NOT NULL, city VARCHAR(120) NOT NULL, population_kind VARCHAR(8) DEFAULT NULL, department VARCHAR(120) DEFAULT NULL, department_code VARCHAR(8) DEFAULT NULL, region VARCHAR(120) DEFAULT NULL, region_code VARCHAR(8) DEFAULT NULL, automate_24 BOOLEAN NOT NULL, services JSON NOT NULL, fuels JSON NOT NULL, source_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX public_fuel_station_source_id ON public_fuel_stations (source_id)');
        $this->addSql('CREATE INDEX public_fuel_station_location_idx ON public_fuel_stations (latitude_micro_degrees, longitude_micro_degrees)');
        $this->addSql('CREATE INDEX public_fuel_station_city_idx ON public_fuel_stations (city, postal_code)');
        $this->addSql('CREATE TABLE public_fuel_station_sync_runs (id UUID NOT NULL, source_url VARCHAR(2048) NOT NULL, status VARCHAR(20) NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, processed_count INT NOT NULL, upserted_count INT NOT NULL, rejected_count INT NOT NULL, error_message TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX public_fuel_station_sync_run_started_idx ON public_fuel_station_sync_runs (started_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE public_fuel_station_sync_runs');
        $this->addSql('DROP TABLE public_fuel_stations');
    }
}
