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

final class Version20260221200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add vehicles table for admin back-office management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE vehicles (id UUID NOT NULL, name VARCHAR(120) NOT NULL, plate_number VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_vehicle_plate_number ON vehicles (plate_number)');
        $this->addSql('CREATE INDEX idx_vehicle_name ON vehicles (name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE vehicles');
    }
}
