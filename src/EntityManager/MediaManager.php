<?php

namespace Softspring\MediaBundle\EntityManager;

use Doctrine\ORM\EntityManagerInterface;
use Softspring\Component\CrudlController\Manager\CrudlEntityManagerTrait;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;

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
        $media->setType($type);

        $this->generateVersionEntities($media);

        return $media;
    }

    public function generateVersionEntities(MediaInterface $media): void
    {
        $typeDefinition = $this->mediaTypeManager->getType($media->getType());

        $originalVersion = $this->mediaVersionManager->createEntity();
        $originalVersion->setVersion('_original');
        $media->addVersion($originalVersion);

        foreach ($typeDefinition['versions'] as $key => $versionOptions) {
            $this->generateVersionEntity($media, $key);
        }
    }

    public function generateVersionEntity(MediaInterface $media, string $versionKey): MediaVersionInterface
    {
        $versionOptions = $this->mediaTypeManager->getTypes()[$media->getType()]['versions'][$versionKey];
        $originalVersion = $media->getVersion('_original');
        $version = $this->mediaVersionManager->createEntity();
        $version->setVersion($versionKey);
        $version->setOriginalVersion($originalVersion);
        $version->setOptions($versionOptions);
        $media->addVersion($version);

        return $version;
    }
}
