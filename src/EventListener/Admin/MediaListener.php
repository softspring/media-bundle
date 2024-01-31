<?php

namespace Softspring\MediaBundle\EventListener\Admin;

use Softspring\Component\CrudlController\Event\CreateEntityEvent;
use Softspring\Component\CrudlController\Event\FormPrepareEvent;
use Softspring\Component\CrudlController\Event\GetResponseEntityEvent;
use Softspring\Component\Events\ViewEvent;
use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Helper\TypeChecker;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\SfsMediaEvents;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

class MediaListener implements EventSubscriberInterface
{
    protected MediaManagerInterface $mediaManager;

    protected MediaTypesCollection $mediaTypesCollection;

    public function __construct(MediaManagerInterface $mediaManager, MediaTypesCollection $mediaTypesCollection)
    {
        $this->mediaManager = $mediaManager;
        $this->mediaTypesCollection = $mediaTypesCollection;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SfsMediaEvents::ADMIN_MEDIAS_LIST_VIEW => 'onListViewAddTypes',
            'sfs_media.search_filter.list_view' => 'onListViewAddTypes',

            SfsMediaEvents::ADMIN_MEDIAS_CREATE_ENTITY => 'onCreateCreateEntity',
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_FORM_PREPARE => 'onCreateFormPrepare',
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_VIEW => 'onCreateViewAddTypeConfig',

            SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_ENTITY => 'onCreateCreateEntity',
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_FORM_PREPARE => 'onCreateFormPrepare',
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_VIEW => 'onCreateViewAddTypeConfig',
            SfsMediaEvents::ADMIN_MEDIAS_CREATE_AJAX_SUCCESS => 'onAjaxCreateSuccess',

            SfsMediaEvents::ADMIN_MEDIAS_READ_VIEW => 'onReadViewAddTypeConfig',
        ];
    }

    public function onListViewAddTypes(ViewEvent $event): void
    {
        $event->getData()['media_types'] = $this->mediaTypesCollection->getTypes();
    }

    public function onCreateCreateEntity(CreateEntityEvent $event): void
    {
        $type = $event->getRequest()->attributes->get('type');
        $media = $this->mediaManager->createEntityForType($type);
        $event->setEntity($media);
    }

    public function onCreateFormPrepare(FormPrepareEvent $event): void
    {
        $type = $event->getRequest()->attributes->get('type');
        $event->setFormOptions($event->getFormOptions() + ['media_type' => $type]);
    }

    public function onCreateViewAddTypeConfig(ViewEvent $event): void
    {
        $event->getData()['type_config'] = $this->mediaTypesCollection->getType($event->getRequest()->attributes->get('type'));
    }

    public function onReadViewAddTypeConfig(ViewEvent $event): void
    {
        /** @var MediaInterface $media */
        $media = $event->getData()['media'];
        $typeConfig = $this->mediaTypesCollection->getType($media->getType());

        $event->getData()['checkVersions'] = TypeChecker::checkMedia($media, $typeConfig);
        $event->getData()['type_config'] = $typeConfig;
    }

    public function onAjaxCreateSuccess(GetResponseEntityEvent $event): void
    {
        $event->setResponse(new Response('', Response::HTTP_CREATED));
    }
}
