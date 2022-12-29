<?php

namespace Softspring\MediaBundle\EntityListener;

use Doctrine\ORM\Event\PreFlushEventArgs;
use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Model\MediaInterface;

class MediaListener
{
    protected MediaManagerInterface $mediaManager;

    public function __construct(MediaManagerInterface $mediaManager)
    {
        $this->mediaManager = $mediaManager;
    }

    public function preFlush(MediaInterface $media, PreFlushEventArgs $eventArgs)
    {
        $media->markCreatedAtNow();
        $this->mediaManager->generateVersionEntities($media);
    }
}
