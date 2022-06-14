<?php

namespace Softspring\MediaBundle\EventListener\Admin;

use Softspring\Component\CrudlController\Event\GetResponseEntityEvent;
use Softspring\Component\Events\ViewEvent;
use Softspring\MediaBundle\Manager\MediaManagerInterface;
use Softspring\MediaBundle\Manager\MediaTypeManagerInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\SfsMediaEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_VIEW => 'onCreateViewAddTypeConfig',
            SfsMediaEvents::ADMIN_MEDIAS_READ_VIEW => 'onReadViewAddTypeConfig',
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
        $this->mediaManager->fillEntityForType($media, $type);
    }

    public function onCreateViewAddTypeConfig(ViewEvent $event): void
    {
        $event->getData()['type_config'] = $this->mediaTypesManager->getType($event->getRequest()->attributes->get('type'));
    }

    public function onReadViewAddTypeConfig(ViewEvent $event): void
    {
        $event->getData()['type_config'] = $this->mediaTypesManager->getType($event->getData()['media']->getType());
    }
}
