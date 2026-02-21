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

final class Version20260221230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add immutable admin audit log table with actor/target/correlation metadata';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_audit_logs (id UUID NOT NULL, actor_id UUID DEFAULT NULL, actor_email VARCHAR(180) DEFAULT NULL, action VARCHAR(120) NOT NULL, target_type VARCHAR(120) NOT NULL, target_id VARCHAR(120) NOT NULL, diff_summary JSON NOT NULL, metadata JSON NOT NULL, correlation_id VARCHAR(80) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_admin_audit_action ON admin_audit_logs (action)');
        $this->addSql('CREATE INDEX idx_admin_audit_target ON admin_audit_logs (target_type, target_id)');
        $this->addSql('CREATE INDEX idx_admin_audit_actor ON admin_audit_logs (actor_id)');
        $this->addSql('CREATE INDEX idx_admin_audit_correlation ON admin_audit_logs (correlation_id)');
        $this->addSql('CREATE INDEX idx_admin_audit_created_at ON admin_audit_logs (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE admin_audit_logs');
    }
}
