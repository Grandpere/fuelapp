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

final class Version20260325172000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add dedicated OCR retry counter on import jobs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_jobs ADD ocr_retry_count INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_jobs DROP ocr_retry_count');
    }
}
