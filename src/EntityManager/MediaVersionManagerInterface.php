<?php

namespace Softspring\MediaBundle\EntityManager;

use Softspring\Component\CrudlController\Manager\CrudlEntityManagerInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;

interface MediaVersionManagerInterface extends CrudlEntityManagerInterface
{
    /**
     * @return MediaVersionInterface
     */
    public function createEntity(): object;

    /**
     * @param MediaVersionInterface $entity
     */
    public function saveEntity(object $entity): void;

    /**
     * @param MediaVersionInterface $entity
     */
    public function deleteEntity(object $entity): void;
}
