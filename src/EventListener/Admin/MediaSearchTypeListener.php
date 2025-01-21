<?php

namespace Softspring\MediaBundle\EventListener\Admin;

use Softspring\Component\CrudlController\Event\FilterEvent;
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
            SfsMediaEvents::ADMIN_MEDIAS_SEARCH_TYPE_FILTER => [
                ['onFilterSearchType', 0],
            ],
            SfsMediaEvents::ADMIN_MEDIAS_SEARCH_TYPE_VIEW => [
                ['onViewAddMediaTypes', 0],
            ],
            // SfsMediaEvents::ADMIN_MEDIAS_SEARCH_TYPE_EXCEPTION => [],
        ];
    }

    public function onFilterSearchType(FilterEvent $event): void
    {
        $filters = $event->getFilters();

        $validTypes = array_filter(explode(',', $event->getRequest()->get('valid_types')));
        if (!empty($validTypes) && empty($filters['type__in'])) {
            $filters['type__in'] = $validTypes;
        }

        $event->setFilters($filters);
    }
}
