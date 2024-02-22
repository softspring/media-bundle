<?php

namespace Softspring\MediaBundle\EventListener\Admin;

use Softspring\MediaBundle\SfsMediaEvents;

class MediaDeleteListener extends AbstractMediaListener
{
    public const ACTION_NAME = 'delete';

    public static function getSubscribedEvents(): array
    {
        return [
            // SfsMediaEvents::ADMIN_MEDIAS_DELETE_INITIALIZE => [],
            // SfsMediaEvents::ADMIN_MEDIAS_DELETE_LOAD_ENTITY => [],
            // SfsMediaEvents::ADMIN_MEDIAS_DELETE_NOT_FOUND => [],
            // SfsMediaEvents::ADMIN_MEDIAS_DELETE_FOUND => [],
            // SfsMediaEvents::ADMIN_MEDIAS_DELETE_FORM_PREPARE => [],
            // SfsMediaEvents::ADMIN_MEDIAS_DELETE_FORM_INIT => [],
            // SfsMediaEvents::ADMIN_MEDIAS_DELETE_FORM_VALID => [],
            // SfsMediaEvents::ADMIN_MEDIAS_DELETE_APPLY => [],
            SfsMediaEvents::ADMIN_MEDIAS_DELETE_SUCCESS => [
                ['onSuccessShowFlash', 10],
            ],
            SfsMediaEvents::ADMIN_MEDIAS_DELETE_FAILURE => [
                ['onFailureShowFlash', 10],
                ['onFailureRedirectToMediaRead', 9],
            ],
            // SfsMediaEvents::ADMIN_MEDIAS_DELETE_FORM_INVALID => [],
            SfsMediaEvents::ADMIN_MEDIAS_DELETE_VIEW => [
                ['onViewAddTypeConfigFromEntity', 0],
            ],
            SfsMediaEvents::ADMIN_MEDIAS_DELETE_EXCEPTION => [
                ['onExceptionShowFlash', 10],
                ['onExceptionRedirectToMediaRead', 9],
            ],
        ];
    }
}
