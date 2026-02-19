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

final class Version20260219142000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename unit_price to deci-cents per liter and migrate existing values';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE receipt_lines RENAME COLUMN unit_price_cents_per_liter TO unit_price_deci_cents_per_liter');
        $this->addSql('UPDATE receipt_lines SET unit_price_deci_cents_per_liter = unit_price_deci_cents_per_liter * 10');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE receipt_lines SET unit_price_deci_cents_per_liter = ROUND(unit_price_deci_cents_per_liter / 10.0)');
        $this->addSql('ALTER TABLE receipt_lines RENAME COLUMN unit_price_deci_cents_per_liter TO unit_price_cents_per_liter');
    }
}
