<?php

namespace Softspring\MediaBundle\Processor;

use Softspring\MediaBundle\Model\MediaVersionInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadedImageSizeProcessor implements ProcessorInterface
{
    public static function getPriority(): int
    {
        return 255;
    }

    public function supports(MediaVersionInterface $version): bool
    {
        return $version->getUpload() instanceof UploadedFile;
    }

    public function process(MediaVersionInterface $version): void
    {
        if (in_array($version->getUpload()->getMimeType(), ['image/png', 'image/gif', 'image/jpeg', 'image/webp'])) {
            [$width, $height] = getimagesize($version->getUpload()->getRealPath());
            $version->setWidth($width);
            $version->setHeight($height);
        }
    }
}
