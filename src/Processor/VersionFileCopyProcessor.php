<?php

namespace Softspring\MediaBundle\Processor;

use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Softspring\MediaBundle\Storage\StorageDriverInterface;
use Softspring\MediaBundle\Tools\AnimationDetector;
use Symfony\Component\HttpFoundation\File\File;

class VersionFileCopyProcessor implements ProcessorInterface
{
    protected StorageDriverInterface $storageDriver;

    public function __construct(StorageDriverInterface $storageDriver, private readonly AnimationDetector $animationDetector = new AnimationDetector())
    {
        $this->storageDriver = $storageDriver;
    }

    public static function getPriority(): int
    {
        return 200;
    }

    public function supports(MediaVersionInterface $version): bool
    {
        return '_original' !== $version->getVersion();
    }

    public function process(MediaVersionInterface $version): void
    {
        if ($version->getUpload() instanceof File) {
            return;
        }

        if (!($originalVersion = $version->getOriginalVersion()) instanceof MediaVersionInterface) {
            return; // exception???
        }

        $extension = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/apng' => 'apng',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
        ][$originalVersion->getFileMimeType()] ?? '';
        $tmpPath = sys_get_temp_dir().'/'.uniqid('sfs_media_').('' !== $extension ? '.'.$extension : '');

        if ($originalVersion->getUpload() instanceof File) {
            // copy file
            copy($originalVersion->getUpload()->getRealPath(), $tmpPath);
            $version->setUpload(new File($tmpPath));
        } else {
            // download original
            $this->storageDriver->download($originalVersion->getUrl(), $tmpPath);
            $version->setUpload(new File($tmpPath));
        }

        // The copied bytes are still the original, before any still-frame conversion.
        $media = $version->getMedia();
        if ('_original' === $originalVersion->getVersion() && $media instanceof MediaInterface && null === $media->getMetadataField('animated') && str_starts_with($originalVersion->getFileMimeType() ?? '', 'image/')) {
            $media->setMetadataField('animated', $this->animationDetector->isAnimated($version->getUpload()->getRealPath()));
        }
    }
}
