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

final class Version20260430212000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-user favorite stations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE favorite_stations (id UUID NOT NULL, owner_id UUID NOT NULL, station_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_favorite_station_owner_station ON favorite_stations (owner_id, station_id)');
        $this->addSql('CREATE INDEX IDX_43D66D717E3C61F9 ON favorite_stations (owner_id)');
        $this->addSql('CREATE INDEX IDX_43D66D7121BDB235 ON favorite_stations (station_id)');
        $this->addSql('ALTER TABLE favorite_stations ADD CONSTRAINT FK_43D66D717E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE favorite_stations ADD CONSTRAINT FK_43D66D7121BDB235 FOREIGN KEY (station_id) REFERENCES stations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE favorite_stations');
    }
}
