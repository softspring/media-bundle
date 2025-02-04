<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250204000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change media type_private default value';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media CHANGE type_private type_private TINYINT(1) DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media CHANGE type_private type_private TINYINT(1) NOT NULL'); // Or whatever the original state was
    }
}
