<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007201832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: roles & permissions (RBAC)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE identity_role (code VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL, permissions JSON NOT NULL, super_admin BOOLEAN DEFAULT false NOT NULL, system BOOLEAN DEFAULT false NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D96443EE77153098 ON identity_role (code)');
        $this->addSql('CREATE TABLE identity_user_role (user_id UUID NOT NULL, role_id UUID NOT NULL, PRIMARY KEY (user_id, role_id))');
        $this->addSql('CREATE INDEX IDX_CF1357EBA76ED395 ON identity_user_role (user_id)');
        $this->addSql('CREATE INDEX IDX_CF1357EBD60322AC ON identity_user_role (role_id)');
        $this->addSql('ALTER TABLE identity_user_role ADD CONSTRAINT FK_CF1357EBA76ED395 FOREIGN KEY (user_id) REFERENCES identity_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE identity_user_role ADD CONSTRAINT FK_CF1357EBD60322AC FOREIGN KEY (role_id) REFERENCES identity_role (id) ON DELETE CASCADE');

        // System roles (fixed ids so environments stay consistent).
        $this->addSql(<<<'SQL'
            INSERT INTO identity_role (id, code, name, permissions, super_admin, system) VALUES
                ('01920000-0000-7000-8000-000000000001', 'super_admin', 'Super Admin', '[]', true, true),
                ('01920000-0000-7000-8000-000000000002', 'user', 'User', '[]', false, true)
            SQL);

        // Carry over the previous JSON roles: everyone gets "user", ROLE_ADMIN becomes "super_admin".
        $this->addSql(<<<'SQL'
            INSERT INTO identity_user_role (user_id, role_id)
            SELECT id, '01920000-0000-7000-8000-000000000002' FROM identity_user
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO identity_user_role (user_id, role_id)
            SELECT id, '01920000-0000-7000-8000-000000000001' FROM identity_user WHERE roles::jsonb @> '["ROLE_ADMIN"]'
            SQL);

        $this->addSql('ALTER TABLE identity_user DROP roles');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE identity_user ADD roles JSON NOT NULL DEFAULT '["ROLE_USER"]'
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE identity_user SET roles = '["ROLE_USER","ROLE_ADMIN"]'
            WHERE id IN (SELECT user_id FROM identity_user_role WHERE role_id = '01920000-0000-7000-8000-000000000001')
            SQL);
        $this->addSql('ALTER TABLE identity_user ALTER roles DROP DEFAULT');
        $this->addSql('DROP TABLE identity_user_role');
        $this->addSql('DROP TABLE identity_role');
    }
}
