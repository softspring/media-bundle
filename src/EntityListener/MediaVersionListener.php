<?php

namespace Softspring\MediaBundle\EntityListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Softspring\MediaBundle\Manager\MediaVersionManagerInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;

class MediaVersionListener
{
    protected MediaVersionManagerInterface $manager;

    public function __construct(MediaVersionManagerInterface $manager)
    {
        $this->manager = $manager;
    }

    public function preRemove(MediaVersionInterface $mediaVersion, LifecycleEventArgs $eventArgs)
    {
        $this->manager->removeFile($mediaVersion);
    }
}
