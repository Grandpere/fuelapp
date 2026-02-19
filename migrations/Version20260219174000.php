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

final class Version20260219174000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store vat_amount_cents on receipts for faster listing queries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE receipts ADD vat_amount_cents INT NOT NULL DEFAULT 0');
        $this->addSql(<<<'SQL'
            UPDATE receipts r
            SET vat_amount_cents = COALESCE((
                SELECT SUM(
                    ROUND(
                        ROUND((rl.unit_price_deci_cents_per_liter * rl.quantity_milli_liters)::numeric / 10000, 0)
                        * rl.vat_rate_percent::numeric
                        / (100 + rl.vat_rate_percent),
                        0
                    )
                )::int
                FROM receipt_lines rl
                WHERE rl.receipt_id = r.id
            ), 0)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE receipts DROP vat_amount_cents');
    }
}
