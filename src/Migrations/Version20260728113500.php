<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728113500 extends AbstractMigration
{
    private const UNIQUE_INDEX = 'uniq_media_version_media_id_version';

    public function getDescription(): string
    {
        return 'Remove duplicate media versions and enforce one version per media';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->addSql(
                <<<'SQL'
                    DELETE FROM media_version AS mv_duplicate
                    USING media_version AS keeper
                    WHERE keeper.media_id = mv_duplicate.media_id
                        AND keeper.version = mv_duplicate.version
                        AND (
                            (mv_duplicate.url IS NULL AND keeper.url IS NOT NULL)
                            OR (
                                (mv_duplicate.url IS NULL) = (keeper.url IS NULL)
                                AND mv_duplicate.id < keeper.id
                            )
                        )
                    SQL,
            );
            $this->addSql(sprintf(
                'CREATE UNIQUE INDEX %s ON media_version (media_id, version)',
                self::UNIQUE_INDEX,
            ));

            return;
        }

        $this->addSql(
            <<<'SQL'
                DELETE mv_duplicate
                FROM media_version AS mv_duplicate
                INNER JOIN media_version AS keeper
                    ON keeper.media_id = mv_duplicate.media_id
                    AND keeper.version = mv_duplicate.version
                    AND (
                        (mv_duplicate.url IS NULL AND keeper.url IS NOT NULL)
                        OR (
                            (mv_duplicate.url IS NULL) = (keeper.url IS NULL)
                            AND mv_duplicate.id < keeper.id
                        )
                    )
                SQL,
        );
        $this->addSql(sprintf(
            'CREATE UNIQUE INDEX %s ON media_version (media_id, version)',
            self::UNIQUE_INDEX,
        ));
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->addSql(sprintf('DROP INDEX %s', self::UNIQUE_INDEX));

            return;
        }

        $this->addSql(sprintf(
            'DROP INDEX %s ON media_version',
            self::UNIQUE_INDEX,
        ));
    }
}
