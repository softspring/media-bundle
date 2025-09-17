<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250915084833 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add alt_texts field to media table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media ADD alt_texts JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media DROP alt_texts');
    }
}
