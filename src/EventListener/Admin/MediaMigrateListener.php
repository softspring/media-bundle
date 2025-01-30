<?php

namespace Softspring\MediaBundle\EventListener\Admin;

use Softspring\Component\CrudlController\Event\ApplyEvent;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\SfsMediaEvents;

class MediaMigrateListener extends AbstractMediaListener
{
    public const ACTION_NAME = 'migrate';

    public static function getSubscribedEvents(): array
    {
        return [
            // SfsMediaEvents::ADMIN_MEDIAS_MIGRATE_INITIALIZE => [],
            // SfsMediaEvents::ADMIN_MEDIAS_MIGRATE_LOAD_ENTITY => [],
            // SfsMediaEvents::ADMIN_MEDIAS_MIGRATE_NOT_FOUND => [],
            // SfsMediaEvents::ADMIN_MEDIAS_MIGRATE_FOUND => [],
            SfsMediaEvents::ADMIN_MEDIAS_MIGRATE_APPLY => [
                ['onMigrateApply', 0],
            ],
            SfsMediaEvents::ADMIN_MEDIAS_MIGRATE_SUCCESS => [
                ['onSuccessShowFlash', 10],
                ['onSuccessRedirectToMediaRead', 9],
            ],
            SfsMediaEvents::ADMIN_MEDIAS_MIGRATE_FAILURE => [
                ['onFailureShowFlash', 0],
                ['onFailureRedirectToMediaRead', 0],
            ],
            SfsMediaEvents::ADMIN_MEDIAS_MIGRATE_EXCEPTION => [
                ['onExceptionShowFlash', 0],
                ['onExceptionRedirectToMediaRead', 0],
            ],
        ];
    }

    public function onMigrateApply(ApplyEvent $event): void
    {
        $this->mediaManager->setMigrating(true);

        /** @var MediaInterface $media */
        $media = $event->getEntity();

        $this->mediaManager->migrate($media);
        $event->setApplied(true);
    }
}
