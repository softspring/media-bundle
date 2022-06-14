<?php

namespace Softspring\MediaBundle\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Softspring\Component\CrudlController\Manager\CrudlEntityManagerTrait;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Symfony\Component\HttpFoundation\File\File;

class MediaManager implements MediaManagerInterface
{
    use CrudlEntityManagerTrait;

    protected EntityManagerInterface $em;

    protected MediaTypeManagerInterface $mediaTypeManager;

    protected MediaVersionManagerInterface $mediaVersionManager;

    public function __construct(EntityManagerInterface $em, MediaTypeManagerInterface $mediaTypeManager, MediaVersionManagerInterface $mediaVersionManager)
    {
        $this->em = $em;
        $this->mediaTypeManager = $mediaTypeManager;
        $this->mediaVersionManager = $mediaVersionManager;
    }

    public function getTargetClass(): string
    {
        return MediaInterface::class;
    }

    public function createEntityForType(string $type): MediaInterface
    {
        /** @var MediaInterface $media */
        $media = $this->createEntity();

        $this->fillEntityForType($media, $type);

        return $media;
    }

    public function fillEntityForType(MediaInterface $media, string $type): void
    {
        $typeDefinition = $this->mediaTypeManager->getType($type);

        $media->setType($type);

        $version = $this->mediaVersionManager->createEntity();
        $version->setVersion('_original');
        $media->addVersion($version);

        foreach ($typeDefinition['versions'] as $key => $config) {
            $version = $this->mediaVersionManager->createEntity();
            $version->setVersion($key);
            $media->addVersion($version);
        }
    }

    public function processVersionsMedias(MediaInterface $media): void
    {
        // persist versions
        foreach ($media->getVersions() as $version) {
            $version->setMedia($media);
        }

        // process original
        $originalVersion = $media->getVersion('_original');

        if (!$originalVersion || !$originalVersion->getUpload()) {
            return;
        }

        $this->mediaVersionManager->fillFieldsFromUploadFile($originalVersion);
        $this->updateStorage($originalVersion);

        // process other versions
        foreach ($this->mediaTypeManager->getTypes()[$media->getType()]['versions'] as $key => $config) {
            if (isset($config['upload_requirements'])) {
                continue;
            }

            $mediaVersion = $this->getAndScaleMediaVersion($media, $key, $config, $originalVersion);
            $this->updateStorage($mediaVersion);
        }
    }

    public function generateVersion(MediaInterface $media, string $version): void
    {
        $versionConfig = $this->mediaTypeManager->getTypes()[$media->getType()]['versions'][$version];

        $originalVersion = $media->getVersion('_original');

        $originalVersion->setUpload(new File($this->mediaVersionManager->downloadFile($originalVersion)));

        $mediaVersion = $this->getAndScaleMediaVersion($media, $version, $versionConfig, $media->getVersion('_original'));
        $this->updateStorage($mediaVersion);

        $originalVersion->setUpload(null);

        $this->mediaVersionManager->saveEntity($mediaVersion);
    }

    public function deleteVersion(MediaVersionInterface $version): void
    {
        $media = $version->getMedia();
        $media->removeVersion($version);
        $this->mediaVersionManager->deleteEntity($version);
        $this->saveEntity($media);
    }

    /**
     * @throws \Exception
     */
    protected function getAndScaleMediaVersion(MediaInterface $media, string $key, array $config, MediaVersionInterface $originalVersion): MediaVersionInterface
    {
        /** @var MediaVersionInterface $mediaVersion */
        $mediaVersion = $media->getVersion($key);
        if (!$mediaVersion) {
            $mediaVersion = $this->mediaVersionManager->createEntity();
            $media->addVersion($mediaVersion);
            $mediaVersion->setVersion($key);
        }

        $scaleWidth = $config['scale_width'] ?? null;
        $scaleHeight = $config['scale_height'] ?? null;
        if (null === $scaleWidth && null === $scaleHeight) {
            throw new \Exception('You should configure scale_width or scale_height');
        } elseif (null !== $scaleWidth && null === $scaleHeight) {
            $scaleHeight = $scaleWidth * $originalVersion->getHeight() / $originalVersion->getWidth();
        } elseif (null === $scaleWidth && null !== $scaleHeight) {
            $scaleWidth = $scaleHeight * $originalVersion->getWidth() / $originalVersion->getHeight();
        }

        $extension = $type = $config['type'];

        $tmpPath = tempnam(sys_get_temp_dir(), 'sfs_media_').'.'.$extension;

        $imagine = new Imagine();
        $gdMedia = $imagine->open($originalVersion->getUpload()->getRealPath());
        $gdMedia->resize(new Box($scaleWidth, $scaleHeight));

        // https://imagine.readthedocs.io/en/stable/usage/introduction.html#save-medias
        $validOptions = array_flip(['png_compression_level', 'webp_quality', 'flatten', 'jpeg_quality', 'resolution-units', 'resolution-x', 'resolution-y', 'resampling-filter']);
        $saveOptions = array_intersect_key($config, $validOptions);
        $gdMedia->save($tmpPath, $saveOptions);

        // save options in database
        $databaseOptions = $config;
        unset($databaseOptions['upload_requirements']);
        $mediaVersion->setOptions($databaseOptions);

        $mediaVersion->setUpload(new File($tmpPath));

        $this->mediaVersionManager->fillFieldsFromUploadFile($mediaVersion);

        $mediaVersion->setMedia($media);

        return $mediaVersion;
    }

    protected function updateStorage(MediaVersionInterface $mediaVersion): void
    {
        if ($mediaVersion->getUrl() && $mediaVersion->getUpload()) {
            $this->mediaVersionManager->removeFile($mediaVersion);
        }

        $this->mediaVersionManager->uploadFile($mediaVersion);
    }
}
