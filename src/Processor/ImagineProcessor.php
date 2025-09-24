<?php

namespace Softspring\MediaBundle\Processor;

use Exception;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImagineProcessor implements ProcessorInterface
{
    public static function getPriority(): int
    {
        return 0;
    }

    /**
     * @throws Exception
     */
    public function supports(MediaVersionInterface $version): bool
    {
        if ('_original' === $version->getVersion()) {
            return false;
        }

        if (isset($version->getOptions()['upload_requirements'])) {
            return false;
        }

        if (!$version->getOriginalVersion()) {
            return false;
        }

        if (!$version->getOptions()) {
            throw new Exception('Processor support method requires version options are initialized');
        }

        if (!in_array($version->getOriginalVersion()->getFileMimeType(), ['image/jpeg', 'image/png', 'image/apng', 'image/gif', 'image/webp', 'image/avif'])) {
            // origin type can not be other than an image
            return false;
        }

        if (!in_array($version->getOptions()['type'], ['jpeg', 'png', 'gif', 'webp', 'avif', 'keep'])) {
            // target type can not be other than an image
            return false;
        }

        return true;
    }

    public function process(MediaVersionInterface $version): void
    {
        if ($version->getUpload() instanceof UploadedFile) {
            return;
        }

        $originalVersion = $version->getOriginalVersion();
        $options = $version->getOptions();
        $saveFormat = $this->getSaveFormat($options, $version);

        if ('image/apng' === $originalVersion->getFileMimeType() && 'apng' === $saveFormat) {
            // APNG files can not be scaled
            return;
        }

        $imagine = new Imagine();
        $gdMedia = $imagine->open($version->getUpload()->getRealPath());

        // scale if needed
        $scaleWidth = $options['scale_width'] ?? null;
        $scaleHeight = $options['scale_height'] ?? null;
        if (null !== $scaleWidth && null === $scaleHeight) {
            $scaleHeight = $scaleWidth * $originalVersion->getHeight() / $originalVersion->getWidth();
        } elseif (null === $scaleWidth && null !== $scaleHeight) {
            $scaleWidth = $scaleHeight * $originalVersion->getWidth() / $originalVersion->getHeight();
        }
        if ($scaleWidth && $scaleHeight) {
            $gdMedia->resize(new Box($scaleWidth, $scaleHeight));
        }

        $version->setWidth($gdMedia->getSize()->getWidth());
        $version->setHeight($gdMedia->getSize()->getHeight());

        // https://imagine.readthedocs.io/en/stable/usage/introduction.html#save-medias
        $validOptions = array_flip(['png_compression_level', 'webp_quality', 'flatten', 'jpeg_quality', 'resolution-units', 'resolution-x', 'resolution-y', 'resampling-filter', 'avif_quality']);
        $saveOptions = array_intersect_key($options, $validOptions);
        $saveOptions['format'] = $saveFormat;

        // change format if needed
        $fileName = $version->getUpload()->getRealPath();
        $fileNameParts = explode('.', $fileName);
        $fileExtension = end($fileNameParts);
        if (isset($options['type']) && $options['type'] !== $fileExtension) {
            $fileNameParts[count($fileNameParts) - 1] = $options['type'];
            $fileName = implode('.', $fileNameParts);
            touch($fileName);
            $version->setUpload(new File($fileName));
        }

        $gdMedia->save($version->getUpload()->getRealPath(), $saveOptions);
    }

    protected function getSaveFormat(array $options, MediaVersionInterface $currentVersion): string
    {
        if ('keep' !== $options['type']) {
            return $options['type'];
        }

        $saveFormat = $currentVersion->getUpload()->getExtension();

        if ('image/apng' === $currentVersion->getOriginalVersion()->getFileMimeType() && 'png' === $saveFormat) {
            return 'apng';
        }

        return $saveFormat;
    }
}
