<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012120933 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Logging: platform of each source (existing ones count as HTTP)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE log_source ADD platform VARCHAR(16) DEFAULT \'http\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE log_source DROP platform');
    }
}
