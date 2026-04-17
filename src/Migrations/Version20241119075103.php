<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\Migrations\AbstractMigration;

final class Version20241119075103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Media name field is optional';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE media ALTER COLUMN name DROP NOT NULL');
            $this->addSql('ALTER TABLE media ADD type_private BOOLEAN NOT NULL DEFAULT FALSE');
            $this->addSql('UPDATE media SET type_private = FALSE');

            return;
        }

        $this->addSql('ALTER TABLE media CHANGE name name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE media ADD type_private TINYINT(1) NOT NULL');
        $this->addSql('UPDATE media SET type_private = 0');
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE media ALTER COLUMN name SET NOT NULL');
            $this->addSql('ALTER TABLE media DROP COLUMN type_private');

            return;
        }

        $this->addSql('ALTER TABLE media CHANGE name name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE media DROP type_private');
    }
}
