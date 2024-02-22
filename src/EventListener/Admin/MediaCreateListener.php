<?php

namespace Softspring\MediaBundle\EventListener\Admin;

use Softspring\Component\CrudlController\Event\ExceptionEvent;
use Softspring\Component\CrudlController\Event\FailureEvent;
use Softspring\MediaBundle\SfsMediaEvents;
use Symfony\Component\Form\FormError;

class MediaCreateListener extends AbstractMediaListener
{
    public const ACTION_NAME = 'create';

    public static function getSubscribedEvents(): array
    {
        return [
            // SfsMediaEvents::ADMIN_MEDIAS_CREATE_INITIALIZE => [],
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_ENTITY => [
                ['onCreateEntityCreateWithType', 0],
            ],
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_FORM_PREPARE => [
                ['onCreateFormPrepareAddMediaTypeOption', 10],
            ],
            // SfsMediaEvents::ADMIN_MEDIAS_CREATE_FORM_INIT => [],
            // SfsMediaEvents::ADMIN_MEDIAS_CREATE_FORM_VALID => [],
            // SfsMediaEvents::ADMIN_MEDIAS_CREATE_APPLY => [],
            // SfsMediaEvents::ADMIN_MEDIAS_CREATE_SUCCESS => [],
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_FAILURE => [
                ['onCreateFailure', 0],
            ],
            // SfsMediaEvents::ADMIN_MEDIAS_CREATE_FORM_INVALID => [],
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_VIEW => [
                ['onViewAddTypeConfigFromRequest', 0],
            ],
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_EXCEPTION => [
                ['onCreateException', 0],
            ],
        ];
    }

    public function onCreateFailure(FailureEvent $event): void
    {
        $event->getForm()->addError(new FormError($event->getException()->getMessage()));
    }

    public function onCreateException(ExceptionEvent $event): void
    {
        $message = $event->getException()->getMessage();
    }
}
