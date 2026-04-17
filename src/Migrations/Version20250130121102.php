<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\Migrations\AbstractMigration;

final class Version20250130121102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sha1 fields to media tables';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE media_version ADD sha1 VARCHAR(40) DEFAULT NULL');
            $this->addSql('ALTER TABLE media ADD COLUMN sha1 VARCHAR(60) DEFAULT NULL');
            $this->addSql('UPDATE media m SET sha1 = (SELECT sha1 FROM media_version mv WHERE m.id=mv.media_id AND version = \'_original\')');

            return;
        }

        $this->addSql('ALTER TABLE media_version ADD sha1 VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE media ADD COLUMN sha1 VARCHAR(60) NULL DEFAULT NULL AFTER type_private');
        $this->addSql('UPDATE media m SET sha1 = (SELECT sha1 FROM media_version mv WHERE m.id=mv.media_id AND version = "_original")');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_version DROP sha1');
        $this->addSql('ALTER TABLE media DROP sha1');
    }
}
