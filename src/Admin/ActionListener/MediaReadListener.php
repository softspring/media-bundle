<?php

namespace Softspring\MediaBundle\Admin\ActionListener;

use Softspring\Component\Events\ViewEvent;
use Softspring\MediaBundle\Helper\TypeChecker;
use Softspring\MediaBundle\Media\CreateFromMedia;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\SfsMediaEvents;
use Symfony\Contracts\Service\Attribute\Required;

class MediaReadListener extends AbstractMediaListener
{
    private CreateFromMedia $createFromMedia;

    #[Required]
    public function setCreateFromMedia(CreateFromMedia $createFromMedia): void
    {
        $this->createFromMedia = $createFromMedia;
    }

    public const ACTION_NAME = 'read';

    public static function getSubscribedEvents(): array
    {
        return [
            // SfsMediaEvents::ADMIN_MEDIAS_READ_INITIALIZE => [],
            // SfsMediaEvents::ADMIN_MEDIAS_READ_LOAD_ENTITY => [],
            // SfsMediaEvents::ADMIN_MEDIAS_READ_NOT_FOUND => [],
            // SfsMediaEvents::ADMIN_MEDIAS_READ_FOUND => [],
            SfsMediaEvents::ADMIN_MEDIAS_READ_VIEW => [
                ['onViewAddTypeConfigFromEntity', 10],
                ['onReadViewAddCheckVersion', 9],
                ['onViewAddMediaTypes', 0],
                ['onViewAddCompatibleTypes', 0],
            ],
            // SfsMediaEvents::ADMIN_MEDIAS_READ_EXCEPTION => [],
        ];
    }

    public function onReadViewAddCheckVersion(ViewEvent $event): void
    {
        /** @var MediaInterface $media */
        $media = $event->getData()['media'];
        $event->getData()['checkVersions'] = TypeChecker::checkMedia($media, $event->getData()['type_config']);
        $event->getData()['duplicates'] = $this->mediaManager->findMediaDuplicates($media);
    }

    public function onViewAddCompatibleTypes(ViewEvent $event): void
    {
        $event->getData()['create_from_types'] = $this->createFromMedia->getCompatibleTypes($event->getData()['media']);
    }
}
