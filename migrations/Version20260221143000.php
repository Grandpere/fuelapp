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

final class Version20260221143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add import_jobs table for async receipt import tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE import_jobs (id UUID NOT NULL, owner_id UUID NOT NULL, status VARCHAR(24) NOT NULL, storage VARCHAR(32) NOT NULL, file_path VARCHAR(512) NOT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(191) NOT NULL, file_size_bytes BIGINT NOT NULL, file_checksum_sha256 VARCHAR(64) NOT NULL, error_payload TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, failed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, retention_until TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_import_jobs_owner_status ON import_jobs (owner_id, status)');
        $this->addSql('CREATE INDEX idx_import_jobs_created_at ON import_jobs (created_at)');
        $this->addSql('ALTER TABLE import_jobs ADD CONSTRAINT FK_60A5AB7A7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_jobs DROP CONSTRAINT FK_60A5AB7A7E3C61F9');
        $this->addSql('DROP TABLE import_jobs');
    }
}
