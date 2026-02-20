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

final class Version20260220120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users table and ownership foreign key on receipts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');

        $this->addSql('ALTER TABLE receipts ADD owner_id UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_1DEBE3A27E3C61F9 ON receipts (owner_id)');
        $this->addSql('ALTER TABLE receipts ADD CONSTRAINT FK_1DEBE3A27E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE receipts DROP CONSTRAINT FK_1DEBE3A27E3C61F9');
        $this->addSql('DROP INDEX IDX_1DEBE3A27E3C61F9');
        $this->addSql('ALTER TABLE receipts DROP owner_id');

        $this->addSql('DROP TABLE users');
    }
}
