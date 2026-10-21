<?php

namespace Softspring\MediaBundle\Processor;

use Exception;
use Imagine\Gd\Imagine as GdImagine;
use Imagine\Image\Box;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Imagick\Imagine as ImagickImagine;
use InvalidArgumentException;
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

        if (!$version->getOriginalVersion() instanceof MediaVersionInterface) {
            return false;
        }

        if (!$version->getOptions()) {
            throw new Exception('Processor support method requires version options are initialized');
        }

        if ($version->getOptions()['animated'] ?? false) {
            return false;
        }

        if (!in_array($version->getOriginalVersion()->getFileMimeType(), ['image/jpeg', 'image/png', 'image/apng', 'image/gif', 'image/webp', 'image/avif'])) {
            // origin type can not be other than an image
            return false;
        }

        // target type can not be other than an image
        return in_array($version->getOptions()['type'], ['jpeg', 'png', 'gif', 'webp', 'avif', 'keep']);
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

        $imagine = $this->createImagine($options['imagine_driver'] ?? 'imagick');
        $image = $imagine->open($version->getUpload()->getRealPath());

        $sourceSize = $image->getSize();
        $targetSize = $this->calculateDimensions($sourceSize, $options);
        $resize = $targetSize->getWidth() !== $sourceSize->getWidth() || $targetSize->getHeight() !== $sourceSize->getHeight();

        $version->setWidth($targetSize->getWidth());
        $version->setHeight($targetSize->getHeight());

        $sourceMime = $originalVersion->getFileMimeType() ?: $version->getUpload()->getMimeType();
        $targetMime = 'image/'.('jpg' === $saveFormat ? 'jpeg' : $saveFormat);
        if (!$resize && ($options['preserve_original_encoding'] ?? true) && $sourceMime === $targetMime
            && !($options['flatten'] ?? false) && !isset($options['resolution-x']) && !isset($options['resolution-y'])) {
            // VersionFileCopyProcessor already made an independent copy. Do not re-encode it.
            return;
        }

        if ($resize) {
            $image->resize($targetSize, $options['resampling-filter'] ?? ImageInterface::FILTER_LANCZOS);
        }

        $version->setWidth($image->getSize()->getWidth());
        $version->setHeight($image->getSize()->getHeight());

        // https://imagine.readthedocs.io/en/stable/usage/introduction.html#save-medias
        $validOptions = array_flip(['png_compression_level', 'webp_quality', 'flatten', 'jpeg_quality', 'resolution-units', 'resolution-x', 'resolution-y', 'resampling-filter', 'avif_quality']);
        $saveOptions = array_intersect_key($options, $validOptions);
        $saveOptions['format'] = $saveFormat;

        // change format if needed
        $fileName = $version->getUpload()->getRealPath();
        $fileNameParts = explode('.', $fileName);
        $fileExtension = end($fileNameParts);
        if ($saveFormat !== $fileExtension) {
            $fileNameParts[count($fileNameParts) - 1] = $saveFormat;
            $fileName = implode('.', $fileNameParts);
            touch($fileName);
            $version->setUpload(new File($fileName));
        }

        $image->save($version->getUpload()->getRealPath(), $saveOptions);
    }

    protected function calculateDimensions(BoxInterface $source, array $options): Box
    {
        $width = $source->getWidth();
        $height = $source->getHeight();
        $requestedWidth = $options['scale_width'] ?? null;
        $requestedHeight = $options['scale_height'] ?? null;
        if (!$requestedWidth && !$requestedHeight) {
            return new Box($width, $height);
        }
        $targetWidth = $requestedWidth ?: $requestedHeight * $width / $height;
        $targetHeight = $requestedHeight ?: $requestedWidth * $height / $width;
        if (!($options['allow_upscale'] ?? false)) {
            $factor = min(1, $width / $targetWidth, $height / $targetHeight);
            $targetWidth *= $factor;
            $targetHeight *= $factor;
        }
        // Two explicit dimensions are treated as a strict shape; tolerance only applies to proportional downsizing.
        if (!$requestedWidth || !$requestedHeight) {
            $ratio = $targetWidth / $width;
            if ($ratio <= 1 && 1 - $ratio <= ($options['resize_tolerance'] ?? 0.02) + 1e-12) {
                return new Box($width, $height);
            }
        }

        return new Box(max(1, (int) floor($targetWidth + 1e-9)), max(1, (int) floor($targetHeight + 1e-9)));
    }

    protected function createImagine(string $driver): ImagineInterface
    {
        return match ($driver) {
            'gd' => new GdImagine(),
            'imagick' => new ImagickImagine(),
            default => throw new InvalidArgumentException(sprintf('Unsupported Imagine driver "%s".', $driver)),
        };
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
