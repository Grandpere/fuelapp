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

final class Version20260222001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add maintenance reminder rules for date/odometer triggers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE maintenance_reminder_rules (id UUID NOT NULL, owner_id UUID NOT NULL, vehicle_id UUID NOT NULL, name VARCHAR(120) NOT NULL, trigger_mode VARCHAR(24) NOT NULL, event_type VARCHAR(32) DEFAULT NULL, interval_days INT DEFAULT NULL, interval_kilometers INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_maintenance_reminder_owner_vehicle ON maintenance_reminder_rules (owner_id, vehicle_id)');
        $this->addSql('ALTER TABLE maintenance_reminder_rules ADD CONSTRAINT FK_MAINTENANCE_REMINDER_OWNER FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE maintenance_reminder_rules ADD CONSTRAINT FK_MAINTENANCE_REMINDER_VEHICLE FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE maintenance_reminder_rules');
    }
}
