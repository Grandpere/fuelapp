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

final class Version20260222103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add analytics read-model tables for daily fuel KPIs and projection freshness state.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE analytics_daily_fuel_kpis (id BIGSERIAL NOT NULL, owner_id UUID NOT NULL, day DATE NOT NULL, vehicle_id UUID DEFAULT NULL, station_id UUID DEFAULT NULL, fuel_type VARCHAR(32) NOT NULL, receipt_count INT NOT NULL, line_count INT NOT NULL, total_cost_cents BIGINT NOT NULL, total_quantity_milli_liters BIGINT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_analytics_daily_fuel_kpi_dim ON analytics_daily_fuel_kpis (owner_id, day, vehicle_id, station_id, fuel_type)');
        $this->addSql('CREATE INDEX idx_analytics_daily_owner_day ON analytics_daily_fuel_kpis (owner_id, day)');
        $this->addSql('CREATE INDEX idx_analytics_daily_owner_vehicle_day ON analytics_daily_fuel_kpis (owner_id, vehicle_id, day)');
        $this->addSql('CREATE INDEX idx_analytics_daily_owner_station_day ON analytics_daily_fuel_kpis (owner_id, station_id, day)');
        $this->addSql('ALTER TABLE analytics_daily_fuel_kpis ADD CONSTRAINT FK_ANALYTICS_KPI_OWNER FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE analytics_daily_fuel_kpis ADD CONSTRAINT FK_ANALYTICS_KPI_VEHICLE FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE analytics_daily_fuel_kpis ADD CONSTRAINT FK_ANALYTICS_KPI_STATION FOREIGN KEY (station_id) REFERENCES stations (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE analytics_projection_states (projection VARCHAR(64) NOT NULL, last_refreshed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, source_max_issued_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, source_receipt_count INT NOT NULL, rows_materialized INT NOT NULL, status VARCHAR(16) NOT NULL, last_error TEXT DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(projection))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE analytics_daily_fuel_kpis');
        $this->addSql('DROP TABLE analytics_projection_states');
    }
}
