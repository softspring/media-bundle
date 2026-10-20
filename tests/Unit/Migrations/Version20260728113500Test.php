<?php

namespace Softspring\MediaBundle\Tests\Unit\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Softspring\MediaBundle\Migrations\Version20260728113500;

class Version20260728113500Test extends TestCase
{
    public static function providePlatforms(): array
    {
        return [
            ['PostgreSQLPlatform', 'USING media_version AS keeper', 'DROP INDEX uniq_media_version_media_id_version'],
            ['MySQLPlatform', 'INNER JOIN media_version AS keeper', 'DROP INDEX uniq_media_version_media_id_version ON media_version'],
        ];
    }

    #[DataProvider('providePlatforms')]
    public function testPlatformSpecificSql(string $platformName, string $deleteClause, string $dropIndex): void
    {
        $platformClass = 'Doctrine\\DBAL\\Platforms\\'.$platformName;
        if (!class_exists($platformClass)) {
            // DBAL 2 uses different class name casing.
            $platformClass = 'Doctrine\\DBAL\\Platforms\\'.str_replace('SQL', 'Sql', $platformName);
        }
        $platform = new $platformClass();
        self::assertInstanceOf(AbstractPlatform::class, $platform);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $up = new Version20260728113500($connection, new NullLogger());
        $up->up(new Schema());
        $queries = $up->getSql();
        self::assertCount(2, $queries);
        self::assertStringContainsString($deleteClause, $queries[0]->getStatement());
        self::assertSame('CREATE UNIQUE INDEX uniq_media_version_media_id_version ON media_version (media_id, version)', $queries[1]->getStatement());

        $down = new Version20260728113500($connection, new NullLogger());
        $down->down(new Schema());
        self::assertCount(1, $down->getSql());
        self::assertSame($dropIndex, $down->getSql()[0]->getStatement());
    }
}
