<?php

namespace Softspring\MediaBundle\EventListener\Admin;

use Softspring\Component\CrudlController\Event\FormPrepareEvent;
use Softspring\Component\CrudlController\Event\GetResponseEntityEvent;
use Softspring\Component\Events\ViewEvent;
use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\EntityManager\MediaTypeManagerInterface;
use Softspring\MediaBundle\Helper\TypeChecker;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\SfsMediaEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class MediaListener implements EventSubscriberInterface
{
    protected MediaManagerInterface $mediaManager;

    protected MediaTypeManagerInterface $mediaTypesManager;

    public function __construct(MediaManagerInterface $mediaManager, MediaTypeManagerInterface $mediaTypesManager)
    {
        $this->mediaManager = $mediaManager;
        $this->mediaTypesManager = $mediaTypesManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SfsMediaEvents::ADMIN_MEDIAS_LIST_VIEW => 'onListViewAddTypes',
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_INITIALIZE => 'onCreateInitializeAddType',
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_FORM_PREPARE => 'onCreateFormPrepare',
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_FORM_PREPARE => 'onCreateFormPrepare',
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_VIEW => 'onCreateViewAddTypeConfig',
            SfsMediaEvents::ADMIN_MEDIAS_READ_VIEW => 'onReadViewAddTypeConfig',
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_SUCCESS => 'onAjaxCreateSuccess',
        ];
    }

    public function onListViewAddTypes(ViewEvent $event): void
    {
        $event->getData()['media_types'] = $this->mediaTypesManager->getTypes();
    }

    public function onCreateInitializeAddType(GetResponseEntityEvent $event): void
    {
        $type = $event->getRequest()->attributes->get('type');

        /** @var MediaInterface $media */
        $media = $event->getEntity();
        $this->mediaManager->createEntityForType($type, $media);
    }

    public function onCreateFormPrepare(FormPrepareEvent $event): void
    {
        $type = $event->getRequest()->attributes->get('type');
        $event->setFormOptions($event->getFormOptions() + ['media_type' => $type]);
    }

    public function onCreateViewAddTypeConfig(ViewEvent $event): void
    {
        $event->getData()['type_config'] = $this->mediaTypesManager->getType($event->getRequest()->attributes->get('type'));
    }

    public function onReadViewAddTypeConfig(ViewEvent $event): void
    {
        /** @var MediaInterface $media */
        $media = $event->getData()['media'];
        $typeConfig = $this->mediaTypesManager->getType($media->getType());

        $event->getData()['checkVersions'] = TypeChecker::checkMedia($media, $typeConfig);
        $event->getData()['type_config'] = $typeConfig;
    }

    public function onAjaxCreateSuccess(GetResponseEntityEvent $event): void
    {
        $event->setResponse(new JsonResponse([]));
    }
}
