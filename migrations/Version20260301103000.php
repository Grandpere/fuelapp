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

final class Version20260301103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional odometer kilometers on receipts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE receipts ADD odometer_kilometers INT DEFAULT NULL');
        $this->addSql('ALTER TABLE receipts ADD CONSTRAINT chk_receipts_odometer_non_negative CHECK (odometer_kilometers IS NULL OR odometer_kilometers >= 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE receipts DROP CONSTRAINT chk_receipts_odometer_non_negative');
        $this->addSql('ALTER TABLE receipts DROP odometer_kilometers');
    }
}
