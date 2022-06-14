<?php

namespace Softspring\MediaBundle\Processor;

use Softspring\MediaBundle\Model\MediaVersionInterface;
use Softspring\MediaBundle\Storage\StorageDriverInterface;
use Symfony\Component\HttpFoundation\File\File;

class VersionFileCopyProcessor implements ProcessorInterface
{
    protected StorageDriverInterface $storageDriver;

    public function __construct(StorageDriverInterface $storageDriver)
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
        if ($version->getUpload()) {
            return;
        }

        if (!$originalVersion = $version->getOriginalVersion()) {
            return; // exception???
        }

        $extension = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ][$originalVersion->getFileMimeType()] ?? '';
        $tmpPath = sys_get_temp_dir().'/'.uniqid('sfs_media_').($extension ? '.'.$extension : '');

        if ($originalVersion->getUpload()) {
            // copy file
            copy($originalVersion->getUpload()->getRealPath(), $tmpPath);
            $version->setUpload(new File($tmpPath));
        } else {
            // download original
            $this->storageDriver->download($originalVersion->getUrl(), $tmpPath);
            $version->setUpload(new File($tmpPath));
        }
    }
}
