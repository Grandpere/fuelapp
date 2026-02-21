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

final class Version20260222004000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add generated maintenance reminders store with deduplication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE maintenance_reminders (id UUID NOT NULL, owner_id UUID NOT NULL, vehicle_id UUID NOT NULL, rule_id UUID NOT NULL, dedup_key VARCHAR(64) NOT NULL, due_at_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, due_at_odometer_kilometers INT DEFAULT NULL, due_by_date BOOLEAN NOT NULL, due_by_odometer BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_maintenance_reminders_owner_created ON maintenance_reminders (owner_id, created_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_maintenance_reminder_dedup_key ON maintenance_reminders (dedup_key)');
        $this->addSql('ALTER TABLE maintenance_reminders ADD CONSTRAINT FK_MAINTENANCE_REMINDER_ENTITY_OWNER FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE maintenance_reminders ADD CONSTRAINT FK_MAINTENANCE_REMINDER_ENTITY_VEHICLE FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE maintenance_reminders ADD CONSTRAINT FK_MAINTENANCE_REMINDER_ENTITY_RULE FOREIGN KEY (rule_id) REFERENCES maintenance_reminder_rules (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE maintenance_reminders');
    }
}
