<?php

namespace Softspring\MediaBundle\Processor;

use Softspring\MediaBundle\Exception\InvalidTypeException;
use Softspring\MediaBundle\Media\NameGeneratorProvider;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Softspring\MediaBundle\Storage\StorageDriverInterface;
use Softspring\MediaBundle\Tools\AnimationDetector;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\HttpFoundation\File\File;

class StoreFileProcessor implements ProcessorInterface
{
    protected MediaTypesCollection $mediaTypesCollection;
    protected NameGeneratorProvider $nameGeneratorProvider;
    protected StorageDriverInterface $storage;

    public function __construct(MediaTypesCollection $mediaTypesCollection, NameGeneratorProvider $nameGeneratorProvider, StorageDriverInterface $storage, private readonly AnimationDetector $animationDetector = new AnimationDetector())
    {
        $this->mediaTypesCollection = $mediaTypesCollection;
        $this->nameGeneratorProvider = $nameGeneratorProvider;
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
        if (!($upload = $version->getUpload()) instanceof File) {
            return;
        }

        if ('_original' === $version->getVersion() && $version->getMedia() instanceof MediaInterface && str_starts_with($upload->getMimeType() ?? '', 'image/')) {
            $version->getMedia()->setMetadataField('animated', $this->animationDetector->isAnimated($upload->getRealPath()));
        }

        // clean database options
        $databaseOptions = $version->getOptions() ?? [];
        $manualUpload = isset($databaseOptions['upload_requirements']);
        unset($databaseOptions['upload_requirements']);
        if ('_original' === $version->getVersion() || $manualUpload) {
            unset($databaseOptions['from']);
        }
        $version->setOptions($databaseOptions);

        if ('image/png' == $upload->getMimeType() && $this->animationDetector->isAnimated($upload->getRealPath())) {
            $version->setFileMimeType('image/apng');
        } else {
            $version->setFileMimeType($upload->getMimeType());
        }

        $version->setFileSize($upload->getSize());
        clearstatcache(); // prevent filesize cache problems returning 0
        $version->setFileSize(filesize($version->getUpload()->getRealPath()));

        if (!$version->getMedia() instanceof MediaInterface || !$version->getMedia()->getType()) {
            throw new InvalidTypeException('Cannot store file for media without type');
        }

        // call generator
        $generator = $this->mediaTypesCollection->getType($version->getMedia()->getType())['generator'];
        $name = $this->nameGeneratorProvider->getGenerator($generator)->generateName($version->getMedia(), $version->getVersion(), $upload);

        $version->setSha1(sha1_file($upload->getRealPath()));
        $version->setUrl($this->storage->store($upload, $name));

        if (!$version->isKeepTmpFile()) {
            // cleanup tmp image
            @unlink($version->getUpload()->getRealPath());
        }

        $version->setUpload(null);
    }
}
