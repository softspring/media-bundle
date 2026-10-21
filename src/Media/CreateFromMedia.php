<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Media;

use InvalidArgumentException;
use RuntimeException;
use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Storage\StorageDriverInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Softspring\MediaBundle\Validator\Constraints\Image;
use Softspring\TranslatableBundle\Model\Translation;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreateFromMedia
{
    public function __construct(
        private MediaTypesCollection $types,
        private MediaManagerInterface $manager,
        private StorageDriverInterface $storage,
        private ValidatorInterface $validator,
    ) {
    }

    /** Metadata-only prefilter: the downloaded original is validated again before creation. */
    public function getCompatibleTypes(MediaInterface $source): array
    {
        $original = $source->getVersion('_original');
        if (!$source->isImage() || !$original?->getUrl() || !$original->getWidth() || !$original->getHeight()) {
            return [];
        }

        $width = $original->getWidth();
        $height = $original->getHeight();
        $ratio = round($width / $height, 2);
        $compatible = [];
        foreach ($this->types->getTypes(false) as $key => $type) {
            if ($key === $source->getType() || 'image' !== $type['type']) {
                continue;
            }
            // A single original cannot supply independently uploaded variants.
            if (array_filter($type['versions'], static fn (array $version): bool => isset($version['upload_requirements']))) {
                continue;
            }
            $requirements = new Image(...($type['upload_requirements'] ?? []));
            foreach (['minWidth' => $width, 'minHeight' => $height, 'minPixels' => $width * $height, 'minRatio' => $ratio] as $option => $value) {
                if (null !== $requirements->$option && $value < $requirements->$option) {
                    continue 2;
                }
            }
            foreach (['maxWidth' => $width, 'maxHeight' => $height, 'maxPixels' => $width * $height, 'maxRatio' => $ratio] as $option => $value) {
                if (null !== $requirements->$option && $value > $requirements->$option) {
                    continue 2;
                }
            }
            if ((!$requirements->allowSquare && $width === $height)
                || (!$requirements->allowLandscape && $width > $height)
                || (!$requirements->allowPortrait && $width < $height)
                || (null !== $requirements->maxSize && (!$original->getFileSize() || $original->getFileSize() > $requirements->maxSize))) {
                continue;
            }
            $mime = $original->getFileMimeType();
            $acceptedMimes = (array) $requirements->mimeTypes;
            // APNG is detected as PNG by Fileinfo, as in the existing upload validator.
            $mimes = 'image/apng' === $mime ? ['image/apng', 'image/png'] : [$mime];
            if ($acceptedMimes && !array_filter($mimes, static fn (?string $mime): bool => null !== $mime && (
                in_array($mime, $acceptedMimes, true) || in_array(explode('/', $mime)[0].'/*', $acceptedMimes, true)
            ))) {
                continue;
            }
            $compatible[$key] = $type;
        }

        return $compatible;
    }

    public function create(MediaInterface $source, string $type): MediaInterface
    {
        $compatible = $this->getCompatibleTypes($source);
        if (!isset($compatible[$type])) {
            throw new InvalidArgumentException('The selected media type is not compatible with this original image.');
        }

        $path = tempnam(sys_get_temp_dir(), 'sfs-media-create-from-');
        if (false === $path) {
            throw new RuntimeException('Cannot create a temporary original image.');
        }
        try {
            $original = $source->getVersion('_original');
            $this->storage->download($original->getUrl(), $path);
            $upload = new UploadedFile($path, $source->getName() ?: 'original', null, null, true);
            $violations = $this->validator->validate($upload, new Image(...($compatible[$type]['upload_requirements'] ?? [])));
            if (count($violations) > 0) {
                $messages = [];
                foreach ($violations as $violation) {
                    $messages[] = $violation->getMessage();
                }
                throw new InvalidArgumentException(implode(' ', $messages));
            }

            $media = $this->manager->createEntityForType($type);
            $media->setName($source->getName());
            $media->setDescription($source->getDescription());
            $media->setPrivate($compatible[$type]['private'] ?? false);
            if ($source->getAltTexts() instanceof Translation) {
                $alts = clone $source->getAltTexts();
                $alts->setTransId(bin2hex(random_bytes(16)));
                $media->setAltTexts($alts);
            }
            $media->getVersion('_original')->setUpload($upload);
            // Normal Doctrine listeners process and store this new media's own files.
            $this->manager->saveEntity($media);

            return $media;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
