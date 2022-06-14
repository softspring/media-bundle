<?php

namespace Softspring\MediaBundle\EntityListener;

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

    public function prePersist(MediaVersionInterface $mediaVersion, LifecycleEventArgs $eventArgs)
    {
        $this->processorProvider->applyProcessors($mediaVersion);
    }

    public function preUpdate(MediaVersionInterface $mediaVersion, LifecycleEventArgs $eventArgs)
    {
        $this->processorProvider->applyProcessors($mediaVersion);
    }

    public function preRemove(MediaVersionInterface $mediaVersion, LifecycleEventArgs $eventArgs)
    {
        $this->storageDriver->remove($mediaVersion->getUrl());
    }
}
