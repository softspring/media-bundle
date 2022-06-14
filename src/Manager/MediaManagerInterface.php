<?php

namespace Softspring\MediaBundle\Manager;

use Softspring\Component\CrudlController\Manager\CrudlEntityManagerInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;

interface MediaManagerInterface extends CrudlEntityManagerInterface
{
    public function createEntityForType(string $type): MediaInterface;

    public function fillEntityForType(MediaInterface $media, string $type): void;

    public function processVersionsMedias(MediaInterface $media): void;

    public function generateVersion(MediaInterface $media, string $version): void;

    public function deleteVersion(MediaVersionInterface $version): void;

    /**
     * @return MediaInterface
     */
    public function createEntity(): object;

    /**
     * @param MediaInterface $entity
     */
    public function saveEntity(object $entity): void;

    /**
     * @param MediaInterface $entity
     */
    public function deleteEntity(object $entity): void;
}
