<?php

namespace Softspring\MediaBundle\Processor;

use Softspring\MediaBundle\Exception\InvalidTypeException;
use Softspring\MediaBundle\Media\NameGenerators;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Softspring\MediaBundle\Storage\StorageDriverInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;

class StoreFileProcessor implements ProcessorInterface
{
    protected MediaTypesCollection $mediaTypesCollection;
    protected NameGenerators $nameGenerators;
    protected StorageDriverInterface $storage;

    public function __construct(MediaTypesCollection $mediaTypesCollection, NameGenerators $nameGenerators, StorageDriverInterface $storage)
    {
        $this->mediaTypesCollection = $mediaTypesCollection;
        $this->nameGenerators = $nameGenerators;
        $this->storage = $storage;
    }

    public static function getPriority(): int
    {
        return -100;
    }

    public function supports(MediaVersionInterface $version): bool
    {
        return true; // save all files if they have an upload file
    }

    /**
     * @throws InvalidTypeException
     */
    public function process(MediaVersionInterface $version): void
    {
        if (!$upload = $version->getUpload()) {
            return;
        }

        // clean database options
        $databaseOptions = $version->getOptions();
        unset($databaseOptions['upload_requirements']);
        $version->setOptions($databaseOptions);

        $version->setFileMimeType($upload->getMimeType());
        $version->setFileSize($upload->getSize());

        // call generator
        $generator = $this->mediaTypesCollection->getType($version->getMedia()->getType())['generator'];
        $name = $this->nameGenerators->getGenerator($generator)->generateName($version->getMedia(), $version->getVersion(), $upload);

        $version->setUrl($this->storage->store($upload, $name));

        if (!$version->isKeepTmpFile()) {
            // cleanup tmp image
            @unlink($version->getUpload()->getRealPath());
        }

        $version->setUpload(null);
    }
}
