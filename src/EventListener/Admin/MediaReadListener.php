<?php

namespace Softspring\MediaBundle\EventListener\Admin;

use Softspring\Component\Events\ViewEvent;
use Softspring\MediaBundle\Helper\TypeChecker;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\SfsMediaEvents;

class MediaReadListener extends AbstractMediaListener
{
    public const ACTION_NAME = 'read';

    public static function getSubscribedEvents(): array
    {
        return [
            // SfsMediaEvents::ADMIN_MEDIAS_READ_INITIALIZE => [],
            // SfsMediaEvents::ADMIN_MEDIAS_READ_LOAD_ENTITY => [],
            // SfsMediaEvents::ADMIN_MEDIAS_READ_NOT_FOUND => [],
            // SfsMediaEvents::ADMIN_MEDIAS_READ_FOUND => [],
            SfsMediaEvents::ADMIN_MEDIAS_READ_VIEW => [
                ['onViewAddTypeConfigFromEntity', 10],
                ['onReadViewAddCheckVersion', 9],
            ],
            // SfsMediaEvents::ADMIN_MEDIAS_READ_EXCEPTION => [],
        ];
    }

    public function onReadViewAddCheckVersion(ViewEvent $event): void
    {
        /** @var MediaInterface $media */
        $media = $event->getData()['media'];
        $event->getData()['checkVersions'] = TypeChecker::checkMedia($media, $event->getData()['type_config']);
    }
}
