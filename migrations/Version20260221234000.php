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

final class Version20260221234000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add maintenance events base table for maintenance bounded context.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE maintenance_events (id UUID NOT NULL, owner_id UUID NOT NULL, vehicle_id UUID NOT NULL, event_type VARCHAR(32) NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, description TEXT DEFAULT NULL, odometer_kilometers INT DEFAULT NULL, total_cost_cents INT DEFAULT NULL, currency_code VARCHAR(3) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_maintenance_owner_occurred_at ON maintenance_events (owner_id, occurred_at)');
        $this->addSql('CREATE INDEX idx_maintenance_vehicle_occurred_at ON maintenance_events (vehicle_id, occurred_at)');
        $this->addSql('ALTER TABLE maintenance_events ADD CONSTRAINT FK_MAINTENANCE_EVENTS_OWNER FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE maintenance_events ADD CONSTRAINT FK_MAINTENANCE_EVENTS_VEHICLE FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE maintenance_events');
    }
}
