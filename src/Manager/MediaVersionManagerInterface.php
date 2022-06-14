<?php

namespace Softspring\MediaBundle\Manager;

use Softspring\Component\CrudlController\Manager\CrudlEntityManagerInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;

interface MediaVersionManagerInterface extends CrudlEntityManagerInterface
{
    public function uploadFile(MediaVersionInterface $mediaVersion): void;

    public function removeFile(MediaVersionInterface $mediaVersion): void;

    public function downloadFile(MediaVersionInterface $mediaVersion): string;

    public function fillFieldsFromUploadFile(MediaVersionInterface $mediaVersion): void;

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
