<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230301000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create media database structure for 5.1';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE image (id CHAR(36) NOT NULL, type_key VARCHAR(50) NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, uploadedAt INT UNSIGNED DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE image_version (id CHAR(36) NOT NULL, image_id CHAR(36) DEFAULT NULL, version VARCHAR(50) DEFAULT NULL, url VARCHAR(1000) DEFAULT NULL, width SMALLINT UNSIGNED DEFAULT NULL, height SMALLINT UNSIGNED DEFAULT NULL, fileSize INT UNSIGNED DEFAULT NULL, fileMimeType VARCHAR(255) DEFAULT NULL, uploadedAt INT UNSIGNED DEFAULT NULL, options JSON DEFAULT NULL, INDEX IDX_2A0C841F3DA5256D (image_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE image_version ADD CONSTRAINT FK_2A0C841F3DA5256D FOREIGN KEY (image_id) REFERENCES image (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE image_version RENAME TO media_version');
        $this->addSql('ALTER TABLE image RENAME TO media');

        $this->addSql('ALTER TABLE media ADD media_type SMALLINT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE media_version DROP FOREIGN KEY FK_2A0C841F3DA5256D');
        $this->addSql('DROP INDEX IDX_2A0C841F3DA5256D ON media_version');
        $this->addSql('ALTER TABLE media_version CHANGE image_id media_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE media_version ADD CONSTRAINT FK_DECB558AEA9FDD75 FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_DECB558AEA9FDD75 ON media_version (media_id)');
        $this->addSql('UPDATE media SET media_type = 1;');

        $this->addSql('ALTER TABLE media CHANGE uploadedAt createdAt INT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE media_version ADD generatedAt INT UNSIGNED DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media CHANGE createdAt uploadedAt INT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE media_version DROP generatedAt');

        $this->addSql('ALTER TABLE media DROP media_type');
        $this->addSql('ALTER TABLE media_version DROP FOREIGN KEY FK_DECB558AEA9FDD75');
        $this->addSql('DROP INDEX IDX_DECB558AEA9FDD75 ON media_version');
        $this->addSql('ALTER TABLE media_version CHANGE media_id image_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE media_version ADD CONSTRAINT FK_2A0C841F3DA5256D FOREIGN KEY (image_id) REFERENCES media (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_2A0C841F3DA5256D ON media_version (image_id)');

        $this->addSql('ALTER TABLE media_version RENAME TO image_version');
        $this->addSql('ALTER TABLE media RENAME TO image');

        $this->addSql('ALTER TABLE image_version DROP FOREIGN KEY FK_2A0C841F3DA5256D');
        $this->addSql('DROP TABLE image');
        $this->addSql('DROP TABLE image_version');
    }
}
