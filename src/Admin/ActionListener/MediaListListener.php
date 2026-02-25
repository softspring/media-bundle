<?php

namespace Softspring\MediaBundle\Admin\ActionListener;

use Softspring\Component\CrudlController\Event\FilterEvent;
use Softspring\Component\CrudlController\Event\ViewEvent;
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
            SfsMediaEvents::ADMIN_MEDIAS_LIST_FILTER => [
                ['onFilter', 0],
            ],
            SfsMediaEvents::ADMIN_MEDIAS_LIST_VIEW => [
                ['onViewAddMediaTypes', 0],
                ['onMediasListViewAddDuplicates', 5],
            ],
            // SfsMediaEvents::ADMIN_MEDIAS_LIST_EXCEPTION => [],
        ];
    }

    public function onFilter(FilterEvent $event): void
    {
        $filters = $event->getFilters();
        $filters['private'] = false;
        $event->setFilters($filters);
    }

    public function onMediasListViewAddDuplicates(ViewEvent $event): void
    {
        $event->getData()['duplicates'] = $this->mediaManager->getDuplicatesStats();
    }
}
