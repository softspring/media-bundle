<?php

namespace Softspring\MediaBundle\EventListener\Admin;

use Softspring\Component\CrudlController\Event\GetResponseEntityEvent;
use Softspring\MediaBundle\SfsMediaEvents;
use Symfony\Component\HttpFoundation\Response;

class MediaCreateAjaxListener extends AbstractMediaListener
{
    public const ACTION_NAME = 'create_ajax';

    public static function getSubscribedEvents(): array
    {
        return [
            // SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_INITIALIZE => [],
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_ENTITY => [
                ['onCreateEntityCreateWithType', 0],
            ],
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_FORM_PREPARE => [
                ['onCreateFormPrepareAddMediaTypeOption', 10],
            ],
            // SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_FORM_INIT => [],
            // SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_FORM_VALID => [],
            // SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_APPLY => [],
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_SUCCESS => [
                ['onCreateAjaxSuccess', 0],
            ],
            // SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_FAILURE => [],
            // SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_FORM_INVALID => [],
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_VIEW => [
                ['onViewAddTypeConfigFromRequest', 0],
            ],
            // SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_EXCEPTION => [],
        ];
    }

    public function onCreateAjaxSuccess(GetResponseEntityEvent $event): void
    {
        $event->setResponse(new Response('', Response::HTTP_CREATED));
    }
}
