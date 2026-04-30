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

final class Version20260430191000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add durable public source link on stations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stations ADD public_source_id VARCHAR(120) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9F39F8F4E15A836D ON stations (public_source_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_9F39F8F4E15A836D');
        $this->addSql('ALTER TABLE stations DROP public_source_id');
    }
}
