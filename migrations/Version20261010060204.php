<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Hand-written: the ORM cannot express partitioning, and log_entry is hidden from it by schema_filter.
 */
final class Version20261010060204 extends AbstractMigration
{
    // Same window the partition task keeps ahead; it takes over daily from here.
    private const int DAYS_BACK = 7;
    private const int DAYS_AHEAD = 7;

    public function getDescription(): string
    {
        return 'Logging: log_entry partitioned by day, with search indexes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        $this->addSql(<<<'SQL'
            CREATE TABLE log_entry (
                id          bigserial   NOT NULL,
                project_id  uuid        NOT NULL,
                source_id   uuid        NOT NULL,
                timestamp   timestamptz NOT NULL,
                received_at timestamptz NOT NULL,
                level       smallint    NOT NULL,
                message     text        NOT NULL,
                context     jsonb       NOT NULL DEFAULT '{}',
                PRIMARY KEY (timestamp, id)
            ) PARTITION BY RANGE (timestamp)
            SQL);

        // Catches lines no daily partition covers; it should stay empty (see PartitionManager).
        $this->addSql('CREATE TABLE log_entry_default PARTITION OF log_entry DEFAULT');

        $this->addSql('CREATE INDEX idx_log_entry_search ON log_entry (project_id, timestamp DESC, id DESC)');
        $this->addSql('CREATE INDEX idx_log_entry_tail ON log_entry (project_id, id)');
        $this->addSql('CREATE INDEX idx_log_entry_message ON log_entry USING gin (message gin_trgm_ops)');
        $this->addSql('CREATE INDEX idx_log_entry_context ON log_entry USING gin (context jsonb_path_ops)');

        $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        for ($day = -self::DAYS_BACK; $day <= self::DAYS_AHEAD; ++$day) {
            $start = $today->modify("{$day} days");
            $this->addSql(\sprintf(
                "CREATE TABLE IF NOT EXISTS log_entry_p%s PARTITION OF log_entry FOR VALUES FROM ('%s') TO ('%s')",
                $start->format('Ymd'),
                $start->format('Y-m-d 00:00:00+00'),
                $start->modify('+1 day')->format('Y-m-d 00:00:00+00'),
            ));
        }
    }

    public function down(Schema $schema): void
    {
        // Drops every partition with it. pg_trgm stays: other schemas may use it.
        $this->addSql('DROP TABLE log_entry');
    }
}
