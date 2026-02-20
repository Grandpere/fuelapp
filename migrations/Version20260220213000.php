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

final class Version20260220213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_identities table for OIDC account linking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_identities (id UUID NOT NULL, user_id UUID NOT NULL, provider VARCHAR(64) NOT NULL, subject VARCHAR(191) NOT NULL, email VARCHAR(180) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3D73A89AA76ED395 ON user_identities (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_identities_provider_subject ON user_identities (provider, subject)');
        $this->addSql('ALTER TABLE user_identities ADD CONSTRAINT FK_3D73A89AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_identities DROP CONSTRAINT FK_3D73A89AA76ED395');
        $this->addSql('DROP TABLE user_identities');
    }
}
