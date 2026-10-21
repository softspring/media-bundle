<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Media;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Media\CreateFromMedia;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Storage\StorageDriverInterface;
use Softspring\MediaBundle\Type\ConfigMediaTypeProvider;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Softspring\TranslatableBundle\Model\Translation;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validation;

class CreateFromMediaTest extends TestCase
{
    private function source(): Media
    {
        $media = new Media();
        $media->setType('source');
        $media->setMediaType(MediaInterface::MEDIA_TYPE_IMAGE);
        $media->setName('Original name');
        $media->setDescription('Original description');
        $media->setAltTexts(Translation::createFromArray(['en' => 'English alt', 'es' => 'Texto español', '_default' => 'es']));
        $version = new MediaVersion('_original', $media);
        $version->setWidth(1200);
        $version->setHeight(600);
        $version->setFileSize(1000);
        $version->setFileMimeType('image/png');
        $version->setUrl('source/original.png');

        return $media;
    }

    private function creator(array $types, ?MediaManagerInterface $manager = null, ?StorageDriverInterface $storage = null): CreateFromMedia
    {
        foreach ($types as &$type) {
            $type += ['type' => 'image', 'private' => false, 'versions' => [], 'upload_requirements' => []];
        }

        return new CreateFromMedia(
            new MediaTypesCollection([new ConfigMediaTypeProvider($types)]),
            $manager ?? $this->createMock(MediaManagerInterface::class),
            $storage ?? $this->createMock(StorageDriverInterface::class),
            Validation::createValidator(),
        );
    }

    public function testCompatibleTypesRespectAllMetadataConstraintsAndDoNotDownload(): void
    {
        $types = [
            'source' => [], 'valid' => ['upload_requirements' => ['minWidth' => 1200, 'minHeight' => 600, 'mimeTypes' => ['image/png'], 'maxSize' => '1k']],
            'wildcard' => ['upload_requirements' => ['mimeTypes' => ['image/*']]],
            'too_wide' => ['upload_requirements' => ['minWidth' => 1201]],
            'too_tall' => ['upload_requirements' => ['minHeight' => 601]],
            'max_width' => ['upload_requirements' => ['maxWidth' => 1199]],
            'max_height' => ['upload_requirements' => ['maxHeight' => 599]],
            'min_pixels' => ['upload_requirements' => ['minPixels' => 720001]],
            'max_pixels' => ['upload_requirements' => ['maxPixels' => 719999]],
            'min_ratio' => ['upload_requirements' => ['minRatio' => 3]],
            'max_ratio' => ['upload_requirements' => ['maxRatio' => 1]],
            'landscape' => ['upload_requirements' => ['allowLandscape' => false]],
            'max_size' => ['upload_requirements' => ['maxSize' => 999]],
            'mime' => ['upload_requirements' => ['mimeTypes' => ['image/avif']]],
            'private' => ['private' => true], 'video' => ['type' => 'video'],
            'manual' => ['versions' => ['poster' => ['upload_requirements' => []]]],
        ];
        $storage = $this->createMock(StorageDriverInterface::class);
        $storage->expects(self::never())->method('download');
        self::assertSame(['valid', 'wildcard'], array_keys($this->creator($types, storage: $storage)->getCompatibleTypes($this->source())));
    }

    public function testMissingOriginalDimensionsAndNonImagesHaveNoDestinations(): void
    {
        $source = $this->source();
        $creator = $this->creator(['target' => []]);
        $source->getVersion('_original')->setWidth(null);
        self::assertSame([], $creator->getCompatibleTypes($source));
        $source->getVersion('_original')->setWidth(1200);
        $source->setMediaType(MediaInterface::MEDIA_TYPE_VIDEO);
        self::assertSame([], $creator->getCompatibleTypes($source));
        $empty = new Media();
        $empty->setMediaType(MediaInterface::MEDIA_TYPE_IMAGE);
        self::assertSame([], $creator->getCompatibleTypes($empty));
    }

    public function testPortraitAndSquareRestrictions(): void
    {
        $creator = $this->creator(['target' => ['upload_requirements' => ['allowPortrait' => false, 'allowSquare' => false]]]);
        $source = $this->source();
        $source->getVersion('_original')->setWidth(500);
        self::assertSame([], $creator->getCompatibleTypes($source));
        $source->getVersion('_original')->setWidth(600);
        self::assertSame([], $creator->getCompatibleTypes($source));
    }

    public function testRejectsForgedIncompatibleTargetsBeforeDownloadingOrSaving(): void
    {
        $storage = $this->createMock(StorageDriverInterface::class);
        $storage->expects(self::never())->method('download');
        $manager = $this->createMock(MediaManagerInterface::class);
        $manager->expects(self::never())->method('saveEntity');
        $this->expectException(InvalidArgumentException::class);
        $this->creator(['target' => ['upload_requirements' => ['minWidth' => 2000]]], $manager, $storage)->create($this->source(), 'target');
    }

    public function testCreatesNewMediaWithCopiedMetadataAndIndependentOriginalAndAlts(): void
    {
        $source = $this->source();
        $new = new Media();
        $new->setType('target');
        new MediaVersion('_original', $new);
        $manager = $this->createMock(MediaManagerInterface::class);
        $manager->expects(self::once())->method('createEntityForType')->with('target')->willReturn($new);
        $temporaryPath = null;
        $storage = $this->createMock(StorageDriverInterface::class);
        $storage->expects(self::once())->method('download')->with('source/original.png', self::anything())
            ->willReturnCallback(static function (string $url, string $path) use (&$temporaryPath): void {
                $temporaryPath = $path;
                copy(__DIR__.'/../../example.png', $path);
            });
        $manager->expects(self::once())->method('saveEntity')->with($new)->willReturnCallback(function (MediaInterface $media) use ($source): void {
            self::assertNotSame($source, $media);
            self::assertNotSame($source->getVersion('_original'), $media->getVersion('_original'));
            self::assertInstanceOf(UploadedFile::class, $media->getVersion('_original')->getUpload());
            self::assertSame(sha1_file(__DIR__.'/../../example.png'), sha1_file($media->getVersion('_original')->getUpload()->getRealPath()));
            self::assertNull($media->getVersion('_original')->getUrl());
        });
        $created = $this->creator(['target' => ['upload_requirements' => ['mimeTypes' => ['image/png']]]], $manager, $storage)->create($source, 'target');
        self::assertSame($new, $created);
        self::assertSame($source->getName(), $created->getName());
        self::assertSame($source->getDescription(), $created->getDescription());
        self::assertFalse($created->getPrivate());
        self::assertNotSame($source->getAltTexts(), $created->getAltTexts());
        self::assertNotSame($source->getAltTexts()->getTransId(), $created->getAltTexts()->getTransId());
        self::assertSame($source->getAltTexts()->getTranslations(), $created->getAltTexts()->getTranslations());
        self::assertSame('es', $created->getAltTexts()->getDefaultLocale());
        $created->getAltTexts()->setTranslation('en', 'Changed');
        self::assertSame('English alt', $source->getAltTexts()->translate('en'));
        self::assertSame('source/original.png', $source->getVersion('_original')->getUrl());
        self::assertFileDoesNotExist($temporaryPath);
    }

    public function testDownloadedFileIsValidatedDespiteCompatibleStoredDimensions(): void
    {
        $manager = $this->createMock(MediaManagerInterface::class);
        $manager->expects(self::never())->method('createEntityForType');
        $manager->expects(self::never())->method('saveEntity');
        $path = null;
        $storage = $this->createMock(StorageDriverInterface::class);
        $storage->method('download')->willReturnCallback(static function (string $url, string $target) use (&$path): void {
            $path = $target;
            copy(__DIR__.'/../../example.png', $target);
        });
        try {
            $this->creator(['target' => ['upload_requirements' => ['minWidth' => 1200]]], $manager, $storage)->create($this->source(), 'target');
            self::fail('Actual image dimensions must be validated.');
        } catch (InvalidArgumentException) {
            self::assertFileDoesNotExist($path);
        }
    }

    public function testDownloadFailuresCleanTemporaryFilesAndNeverPersist(): void
    {
        $path = null;
        $storage = $this->createMock(StorageDriverInterface::class);
        $storage->method('download')->willReturnCallback(static function (string $url, string $target) use (&$path): void {
            $path = $target;
            throw new RuntimeException('Download failed');
        });
        $manager = $this->createMock(MediaManagerInterface::class);
        $manager->expects(self::never())->method('saveEntity');
        try {
            $this->creator(['target' => []], $manager, $storage)->create($this->source(), 'target');
            self::fail('Expected storage failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Download failed', $exception->getMessage());
            self::assertFileDoesNotExist($path);
        }
    }
}
