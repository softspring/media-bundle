<?php

namespace Softspring\MediaBundle\EntityListener;

use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Softspring\MediaBundle\Processor\ProcessorProvider;
use Softspring\MediaBundle\Storage\StorageDriverInterface;

class MediaVersionListener
{
    protected StorageDriverInterface $storageDriver;
    protected ProcessorProvider $processorProvider;

    public function __construct(StorageDriverInterface $storageDriver, ProcessorProvider $processorProvider)
    {
        $this->storageDriver = $storageDriver;
        $this->processorProvider = $processorProvider;
    }

    public function prePersist(MediaVersionInterface $mediaVersion, LifecycleEventArgs $eventArgs): void
    {
        $this->processorProvider->applyProcessors($mediaVersion);
    }

    public function preUpdate(MediaVersionInterface $mediaVersion, LifecycleEventArgs $eventArgs): void
    {
        $this->processorProvider->applyProcessors($mediaVersion);
    }

    public function preRemove(MediaVersionInterface $mediaVersion, LifecycleEventArgs $eventArgs): void
    {
        $this->storageDriver->remove($mediaVersion->getUrl());
    }

    /**
     * On update, if original version is uploaded, remove all versions urls to force regenerating them.
     */
    public function onFlush($eventArgs): void
    {
        $em = $eventArgs->getObjectManager();

        /** @var UnitOfWork $uow */
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof MediaVersionInterface) {
                continue;
            }
            if ('_original' !== $entity->getVersion() || !$entity->getUpload()) {
                continue;
            }

            $entity->setUrl(null);

            foreach ($entity->getMedia()->getVersions() as $version) {
                if ($version === $entity) {
                    continue;
                }

                if (!$version->getGeneratedAt()) {
                    continue;
                }

                $version->setUrl(null);
                $version->setOriginalVersion($entity);
                $uow->computeChangeSet($em->getClassMetadata(get_class($version)), $version);
            }
        }
    }
}
