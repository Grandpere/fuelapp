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

final class Version20260222012000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add maintenance planned costs table for planned vs actual model.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE maintenance_planned_costs (id UUID NOT NULL, owner_id UUID NOT NULL, vehicle_id UUID NOT NULL, label VARCHAR(160) NOT NULL, event_type VARCHAR(32) DEFAULT NULL, planned_for TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, planned_cost_cents INT NOT NULL, currency_code VARCHAR(3) NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_maintenance_planned_owner_date ON maintenance_planned_costs (owner_id, planned_for)');
        $this->addSql('CREATE INDEX idx_maintenance_planned_vehicle_date ON maintenance_planned_costs (vehicle_id, planned_for)');
        $this->addSql('ALTER TABLE maintenance_planned_costs ADD CONSTRAINT FK_MAINTENANCE_PLANNED_OWNER FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE maintenance_planned_costs ADD CONSTRAINT FK_MAINTENANCE_PLANNED_VEHICLE FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE maintenance_planned_costs');
    }
}
