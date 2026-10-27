<?php

namespace Softspring\MediaBundle\EntityListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Softspring\MediaBundle\Processor\ProcessorProvider;
use Softspring\MediaBundle\Storage\StorageDriverInterface;
use Symfony\Component\HttpFoundation\File\File;

class MediaVersionListener
{
    public function __construct(
        protected StorageDriverInterface $storageDriver,
        protected ProcessorProvider $processorProvider,
        protected MediaManagerInterface $mediaManager,
    ) {
    }

    public function prePersist(MediaVersionInterface $mediaVersion, LifecycleEventArgs $eventArgs): void
    {
        $this->processUpload($mediaVersion, $eventArgs);
    }

    public function preUpdate(MediaVersionInterface $mediaVersion, LifecycleEventArgs $eventArgs): void
    {
        $this->processUpload($mediaVersion, $eventArgs);
    }

    private function processUpload(MediaVersionInterface $mediaVersion, LifecycleEventArgs $eventArgs): void
    {
        $media = $mediaVersion->getMedia();
        $metadata = $media?->getMetadata();
        $this->processorProvider->applyProcessors($mediaVersion);
        if (!$media instanceof MediaInterface || $metadata === $media->getMetadata()) {
            return;
        }

        // Processors run during flush, after the parent media's change set may
        // already have been computed. Include newly detected animation metadata.
        $em = $eventArgs->getObjectManager();
        if (!$em instanceof EntityManagerInterface) {
            return;
        }
        $uow = $em->getUnitOfWork();
        if ([] !== $uow->getOriginalEntityData($media)) {
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(get_class($media)), $media);
        }
    }

    public function preRemove(MediaVersionInterface $mediaVersion, LifecycleEventArgs $eventArgs): void
    {
        $mediaVersion->getUrl() && $this->storageDriver->remove($mediaVersion->getUrl());
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
            if ('_original' !== $entity->getVersion() || !$entity->getUpload() instanceof File) {
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
