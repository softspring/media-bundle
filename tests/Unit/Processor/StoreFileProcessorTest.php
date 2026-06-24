<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Exception\InvalidTypeException;
use Softspring\MediaBundle\Media\NameGeneratorInterface;
use Softspring\MediaBundle\Media\NameGeneratorProvider;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Processor\StoreFileProcessor;
use Softspring\MediaBundle\Storage\StorageDriverInterface;
use Softspring\MediaBundle\Type\MediaTypeProviderInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class StoreFileProcessorTest extends TestCase
{
    public function testPriorityAndSupports(): void
    {
        $processor = $this->createProcessor(new StoreFileStorageDriver());

        $this->assertSame(-100, StoreFileProcessor::getPriority());
        $this->assertTrue($processor->supports(new MediaVersion()));
    }

    public function testDoesNothingWithoutUpload(): void
    {
        $storage = new StoreFileStorageDriver();
        $version = new MediaVersion('_original');

        $processor = $this->createProcessor($storage);
        $processor->process($version);

        $this->assertNull($version->getUrl());
        $this->assertNull($storage->storedName);
    }

    public function testFailsWhenMediaTypeCannotBeResolved(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'media-store-missing-type-');
        file_put_contents($path, 'content');

        $version = new MediaVersion('_original');
        $version->setUpload(new File($path), true);
        $version->setOptions([]);

        $processor = $this->createProcessor(new StoreFileStorageDriver());

        $this->expectException(InvalidTypeException::class);
        $this->expectExceptionMessage('Invalid "Cannot store file for media without type" media type. Check your configuration');

        $processor->process($version);
    }

    public function testStoresUploadAndCleansDatabaseOptions(): void
    {
        $storage = new StoreFileStorageDriver();
        $sourcePath = sys_get_temp_dir().'/media-store-upload-'.uniqid().'.png';
        copy(__DIR__.'/../../example.png', $sourcePath);

        $media = new Media();
        $media->setType('image');

        $version = new MediaVersion('_original', $media);
        $version->setOptions([
            'upload_requirements' => ['maxSize' => '2M'],
            'from' => '_original',
            'quality' => 90,
        ]);
        $version->setUpload(new UploadedFile($sourcePath, 'example.png', null, null, true), true);

        $processor = $this->createProcessor($storage);
        $processor->process($version);

        $this->assertSame(['quality' => 90], $version->getOptions());
        $this->assertSame('image/png', $version->getFileMimeType());
        $this->assertSame(filesize($sourcePath), $version->getFileSize());
        $this->assertSame(sha1_file($sourcePath), $version->getSha1());
        $this->assertSame(sha1_file($sourcePath), $media->getSha1());
        $this->assertSame('generated-_original-'.basename($sourcePath), $storage->storedName);
        $this->assertSame('stored://generated-_original-'.basename($sourcePath), $version->getUrl());
        $this->assertNull($version->getUpload());
        $this->assertFileExists($sourcePath);
    }

    private function createProcessor(StoreFileStorageDriver $storage): StoreFileProcessor
    {
        $mediaTypes = new MediaTypesCollection([new StoreFileMediaTypeProvider()]);
        $generators = new NameGeneratorProvider([new StoreFileNameGenerator()]);

        return new StoreFileProcessor($mediaTypes, $generators, $storage);
    }
}

class StoreFileNameGenerator implements NameGeneratorInterface
{
    public function generateName(MediaInterface $media, string $version, File $file): string
    {
        return "generated-$version-".$file->getBasename();
    }

    public static function getPriority(): int
    {
        return 0;
    }
}

class StoreFileMediaTypeProvider implements MediaTypeProviderInterface
{
    public function getTypes(): array
    {
        return [
            'image' => [
                'generator' => StoreFileNameGenerator::class,
            ],
        ];
    }

    public static function getPriority(): int
    {
        return 0;
    }
}

class StoreFileStorageDriver implements StorageDriverInterface
{
    public ?string $storedName = null;

    public function store(File $file, string $destName): string
    {
        $this->storedName = $destName;

        return "stored://$destName";
    }

    public function remove(string $fileName): void
    {
    }

    public function download(string $fileName, string $destPath): void
    {
    }

    public function url(string $fileName): string
    {
        return $fileName;
    }
}
