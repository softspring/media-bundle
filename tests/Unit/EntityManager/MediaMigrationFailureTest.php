<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\EntityManager;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\EntityManager\MediaManager;
use Softspring\MediaBundle\EntityManager\MediaVersionManagerInterface;
use Softspring\MediaBundle\Exception\MigrateMediaException;
use Softspring\MediaBundle\Storage\StorageDriverInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\Console\Output\BufferedOutput;

class MediaMigrationFailureTest extends TestCase
{
    public function testVersionFailureIsNotSwallowedWhenConsoleOutputIsPresent(): void
    {
        $config = ['versions' => ['small' => ['from' => '_original']]];
        $types = $this->createMock(MediaTypesCollection::class);
        $types->method('getType')->willReturn($config);
        $types->method('getTypes')->willReturn(['content' => $config]);
        $versions = $this->createMock(MediaVersionManagerInterface::class);
        $versions->method('createEntity')->willReturn(new MediaVersion());
        $versions->method('saveEntity')->willThrowException(new RuntimeException('Encoder failed'));
        $manager = new MediaManager($this->createMock(EntityManagerInterface::class), $types, $versions, $this->createMock(StorageDriverInterface::class));
        $media = new Media();
        $media->setType('content');
        $original = new MediaVersion();
        $original->setVersion('_original');
        $media->addVersion($original);

        $this->expectException(MigrateMediaException::class);
        $this->expectExceptionMessage('Error creating version small');
        $manager->migrate($media, new BufferedOutput());
    }
}
