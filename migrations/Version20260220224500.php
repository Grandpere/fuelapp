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

final class Version20260220224500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add station geocoding status and diagnostics fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE stations ADD geocoding_status VARCHAR(16) DEFAULT 'pending' NOT NULL");
        $this->addSql('ALTER TABLE stations ADD geocoding_requested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE stations ADD geocoded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE stations ADD geocoding_failed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE stations ADD geocoding_last_error TEXT DEFAULT NULL');

        $this->addSql("UPDATE stations SET geocoding_status = 'success', geocoded_at = CURRENT_TIMESTAMP WHERE latitude_micro_degrees IS NOT NULL AND longitude_micro_degrees IS NOT NULL");
        $this->addSql("UPDATE stations SET geocoding_status = 'pending', geocoding_requested_at = COALESCE(geocoding_requested_at, CURRENT_TIMESTAMP) WHERE geocoding_status = 'pending'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stations DROP geocoding_status');
        $this->addSql('ALTER TABLE stations DROP geocoding_requested_at');
        $this->addSql('ALTER TABLE stations DROP geocoded_at');
        $this->addSql('ALTER TABLE stations DROP geocoding_failed_at');
        $this->addSql('ALTER TABLE stations DROP geocoding_last_error');
    }
}
