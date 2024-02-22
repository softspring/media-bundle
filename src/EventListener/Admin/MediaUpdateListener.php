<?php

namespace Softspring\MediaBundle\EventListener\Admin;

use Softspring\Component\CrudlController\Event\FormPrepareEvent;
use Softspring\MediaBundle\SfsMediaEvents;

class MediaUpdateListener extends AbstractMediaListener
{
    public const ACTION_NAME = 'update';

    public static function getSubscribedEvents(): array
    {
        return [
            // SfsMediaEvents::ADMIN_MEDIAS_UPDATE_INITIALIZE => [],
            // SfsMediaEvents::ADMIN_MEDIAS_UPDATE_LOAD_ENTITY => [],
            // SfsMediaEvents::ADMIN_MEDIAS_UPDATE_NOT_FOUND => [],
            // SfsMediaEvents::ADMIN_MEDIAS_UPDATE_FOUND => [],
            SfsMediaEvents::ADMIN_MEDIAS_UPDATE_FORM_PREPARE => [
                ['onFormPrepare', 0],
            ],
            // SfsMediaEvents::ADMIN_MEDIAS_UPDATE_FORM_INIT => [],
            // SfsMediaEvents::ADMIN_MEDIAS_UPDATE_FORM_VALID => [],
            // SfsMediaEvents::ADMIN_MEDIAS_UPDATE_APPLY => [],
            SfsMediaEvents::ADMIN_MEDIAS_UPDATE_SUCCESS => [
                ['onSuccessShowFlash', 10],
                ['onSuccessRedirectToMediaRead', 9],
            ],
            SfsMediaEvents::ADMIN_MEDIAS_UPDATE_FAILURE => [
                ['onFormFailureAddError', 0],
            ],
            // SfsMediaEvents::ADMIN_MEDIAS_UPDATE_FORM_INVALID => [],
            SfsMediaEvents::ADMIN_MEDIAS_UPDATE_VIEW => [
                ['onViewAddTypeConfigFromEntity', 0],
            ],
            // SfsMediaEvents::ADMIN_MEDIAS_UPDATE_EXCEPTION => [],
        ];
    }

    public function onFormPrepare(FormPrepareEvent $event): void
    {
        $event->setFormOptions([
            'media_type' => $event->getEntity()->getType(),
        ]);
    }
}
