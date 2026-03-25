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

final class Version20260325193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize legacy unleaded95 fuel type aliases to sp95.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE receipt_lines SET fuel_type = 'sp95' WHERE fuel_type = 'unleaded95'");
        $this->addSql("UPDATE analytics_daily_fuel_kpis SET fuel_type = 'sp95' WHERE fuel_type = 'unleaded95'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE receipt_lines SET fuel_type = 'unleaded95' WHERE fuel_type = 'sp95'");
        $this->addSql("UPDATE analytics_daily_fuel_kpis SET fuel_type = 'unleaded95' WHERE fuel_type = 'sp95'");
    }
}
