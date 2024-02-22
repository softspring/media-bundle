<?php

namespace Softspring\MediaBundle\EventListener\Admin;

use Softspring\MediaBundle\SfsMediaEvents;

class MediaListListener extends AbstractMediaListener
{
    public const ACTION_NAME = 'list';

    public static function getSubscribedEvents(): array
    {
        return [
            // SfsMediaEvents::ADMIN_MEDIAS_LIST_INITIALIZE => [],
            // SfsMediaEvents::ADMIN_MEDIAS_LIST_FILTER_FORM_PREPARE => [],
            // SfsMediaEvents::ADMIN_MEDIAS_LIST_FILTER_FORM_INIT => [],
            // SfsMediaEvents::ADMIN_MEDIAS_LIST_FILTER => [],
            SfsMediaEvents::ADMIN_MEDIAS_LIST_VIEW => [
                ['onViewAddMediaTypes', 0],
            ],
            // SfsMediaEvents::ADMIN_MEDIAS_LIST_EXCEPTION => [],
        ];
    }
}
