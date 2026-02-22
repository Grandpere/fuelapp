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

final class Version20260222150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add analytics KPI index for owner/fuel/day filters.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_analytics_daily_owner_fuel_day ON analytics_daily_fuel_kpis (owner_id, fuel_type, day)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_analytics_daily_owner_fuel_day');
    }
}
