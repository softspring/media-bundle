<?php

namespace Softspring\MediaBundle\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Softspring\Component\CrudlController\Manager\CrudlEntityManagerTrait;
use Softspring\MediaBundle\Media\NameGenerators;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Softspring\MediaBundle\Storage\StorageDriverInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\HttpFoundation\File\File;

class MediaVersionManager implements MediaVersionManagerInterface
{
    use CrudlEntityManagerTrait;

    protected EntityManagerInterface $em;

    protected StorageDriverInterface $storage;

    protected NameGenerators $nameGenerators;

    protected MediaTypesCollection $mediaTypesCollection;

    public function __construct(EntityManagerInterface $em, StorageDriverInterface $storage, NameGenerators $nameGenerators, MediaTypesCollection $mediaTypesCollection)
    {
        $this->em = $em;
        $this->storage = $storage;
        $this->nameGenerators = $nameGenerators;
        $this->mediaTypesCollection = $mediaTypesCollection;
    }

    public function getTargetClass(): string
    {
        return MediaVersionInterface::class;
    }

    public function uploadFile(MediaVersionInterface $mediaVersion): void
    {
        $upload = $mediaVersion->getUpload();

        if (!$upload instanceof File) {
            return;
        }

        // call generator
        $generator = $this->mediaTypesCollection->getType($mediaVersion->getMedia()->getType())['generator'];
        $name = $this->nameGenerators->getGenerator($generator)->generateName($mediaVersion->getMedia(), $mediaVersion->getVersion(), $upload);
        // upload file
        $mediaVersion->setUrl($this->storage->store($upload, $name));
    }

    public function removeFile(MediaVersionInterface $mediaVersion): void
    {
        $this->storage->remove($mediaVersion->getUrl());
    }

    public function downloadFile(MediaVersionInterface $mediaVersion): string
    {
        $tempName = tempnam(sys_get_temp_dir(), 'sfs_media');
        $this->storage->download($mediaVersion->getUrl(), $tempName);

        return $tempName;
    }

    public function fillFieldsFromUploadFile(MediaVersionInterface $mediaVersion): void
    {
        if (!$upload = $mediaVersion->getUpload()) {
            return;
        }

        $mediaVersion->setFileMimeType($upload->getMimeType());
        $mediaVersion->setFileSize($upload->getSize());
        [$width, $height] = getmediasize($upload->getRealPath());
        $mediaVersion->setWidth($width);
        $mediaVersion->setHeight($height);
    }
}
