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

final class Version20260221213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link vehicles to users and allow receipts to reference a vehicle';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vehicles ADD owner_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicles ADD CONSTRAINT FK_8F36B67A7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8F36B67A7E3C61F9 ON vehicles (owner_id)');
        $this->addSql('DROP INDEX IF EXISTS uniq_vehicle_plate_number');
        $this->addSql('CREATE UNIQUE INDEX uniq_vehicle_owner_plate_number ON vehicles (owner_id, plate_number)');

        $this->addSql('ALTER TABLE receipts ADD vehicle_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE receipts ADD CONSTRAINT FK_BF4E0DCA5456D7B FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_BF4E0DCA5456D7B ON receipts (vehicle_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE receipts DROP CONSTRAINT FK_BF4E0DCA5456D7B');
        $this->addSql('DROP INDEX IDX_BF4E0DCA5456D7B');
        $this->addSql('ALTER TABLE receipts DROP vehicle_id');

        $this->addSql('DROP INDEX uniq_vehicle_owner_plate_number');
        $this->addSql('DROP INDEX IDX_8F36B67A7E3C61F9');
        $this->addSql('ALTER TABLE vehicles DROP CONSTRAINT FK_8F36B67A7E3C61F9');
        $this->addSql('ALTER TABLE vehicles DROP owner_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_vehicle_plate_number ON vehicles (plate_number)');
    }
}
