<?php

namespace Softspring\MediaBundle\EntityManager;

use Softspring\Component\CrudlController\Manager\CrudlEntityManagerInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface MediaManagerInterface extends CrudlEntityManagerInterface
{
    public function createEntityForType(string $type, ?MediaInterface $media = null): MediaInterface;

    public function generateVersionEntities(MediaInterface $media): void;

    public function generateVersionEntity(MediaInterface $media, string $versionKey): MediaVersionInterface;

    public function migrate(MediaInterface $media, ?OutputInterface $output = null): void;

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
