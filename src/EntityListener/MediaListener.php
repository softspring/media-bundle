<?php

namespace Softspring\MediaBundle\EntityListener;

use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Model\MediaInterface;

class MediaListener
{
    protected MediaManagerInterface $mediaManager;

    public function __construct(MediaManagerInterface $mediaManager)
    {
        $this->mediaManager = $mediaManager;
    }

    public function preFlush(MediaInterface $media, PreFlushEventArgs $eventArgs): void
    {
        $this->mediaManager->generateVersionEntities($media);
    }

    public function prePersist(MediaInterface $media, PrePersistEventArgs $eventArgs): void
    {
        $media->markCreatedAtNow();
    }

    public function preUpdate(MediaInterface $media, PreUpdateEventArgs $eventArgs): void
    {
    }
}
