<?php

namespace Softspring\MediaBundle\EventListener\Admin;

use Softspring\MediaBundle\SfsMediaEvents;

class MediaSearchTypeListener extends AbstractMediaListener
{
    public const ACTION_NAME = 'search_type';

    public static function getSubscribedEvents(): array
    {
        return [
            // SfsMediaEvents::ADMIN_MEDIAS_SEARCH_TYPE_INITIALIZE => [],
            // SfsMediaEvents::ADMIN_MEDIAS_SEARCH_TYPE_FILTER_FORM_PREPARE => [],
            // SfsMediaEvents::ADMIN_MEDIAS_SEARCH_TYPE_FILTER_FORM_INIT => [],
            // SfsMediaEvents::ADMIN_MEDIAS_SEARCH_TYPE_FILTER => [],
            SfsMediaEvents::ADMIN_MEDIAS_SEARCH_TYPE_VIEW => [
                ['onViewAddMediaTypes', 0],
            ],
            // SfsMediaEvents::ADMIN_MEDIAS_SEARCH_TYPE_EXCEPTION => [],
        ];
    }
}
