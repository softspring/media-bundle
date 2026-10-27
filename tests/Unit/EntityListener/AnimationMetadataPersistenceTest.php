<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\EntityListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\EntityListener\MediaVersionListener;
use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Processor\ProcessorProvider;
use Softspring\MediaBundle\Storage\StorageDriverInterface;

final class AnimationMetadataPersistenceTest extends TestCase
{
    public function testAnimationDetectedDuringFlushIsIncludedInTheMediaChangeSet(): void
    {
        $media = new Media();
        $version = new MediaVersion('_original', $media);
        $processors = $this->createMock(ProcessorProvider::class);
        $processors->expects(self::once())->method('applyProcessors')->with($version)->willReturnCallback(
            static function () use ($media): void {
                $media->setMetadataField('animated', true);
            }
        );
        $metadata = new ClassMetadata(Media::class);
        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('getOriginalEntityData')->with($media)->willReturn(['metadata' => null]);
        $uow->expects(self::once())->method('recomputeSingleEntityChangeSet')->with($metadata, $media);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('getClassMetadata')->with(Media::class)->willReturn($metadata);
        $listener = new MediaVersionListener(
            $this->createMock(StorageDriverInterface::class),
            $processors,
            $this->createMock(MediaManagerInterface::class),
        );

        $listener->preUpdate($version, new LifecycleEventArgs($version, $em));
        self::assertTrue($media->getMetadataField('animated'));
    }
}
