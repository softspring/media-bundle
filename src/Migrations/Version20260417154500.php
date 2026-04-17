<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417154500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix media.type_private column type for PostgreSQL (smallint to boolean)';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return;
        }

        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'media'
          AND column_name = 'type_private'
          AND data_type <> 'boolean'
    ) THEN
        ALTER TABLE media ALTER COLUMN type_private DROP DEFAULT;
        ALTER TABLE media ALTER COLUMN type_private TYPE BOOLEAN USING (type_private <> 0);
        ALTER TABLE media ALTER COLUMN type_private SET DEFAULT FALSE;
    END IF;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return;
        }

        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'media'
          AND column_name = 'type_private'
          AND data_type = 'boolean'
    ) THEN
        ALTER TABLE media ALTER COLUMN type_private DROP DEFAULT;
        ALTER TABLE media ALTER COLUMN type_private TYPE SMALLINT USING (CASE WHEN type_private THEN 1 ELSE 0 END);
        ALTER TABLE media ALTER COLUMN type_private SET DEFAULT 0;
    END IF;
END $$;
SQL);
    }
}
