<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Processor\VersionFileCopyProcessor;
use Softspring\MediaBundle\Storage\StorageDriverInterface;
use Symfony\Component\HttpFoundation\File\File;

class VersionFileCopyProcessorTest extends TestCase
{
    public function testPriority(): void
    {
        $this->assertSame(200, VersionFileCopyProcessor::getPriority());
    }

    public function testSupportsGeneratedVersionsOnly(): void
    {
        $processor = new VersionFileCopyProcessor(new VersionCopyStorageDriver());

        $this->assertFalse($processor->supports(new MediaVersion('_original')));
        $this->assertTrue($processor->supports(new MediaVersion('thumbnail')));
    }

    public function testDoesNothingWhenVersionAlreadyHasUpload(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'media-version-source-');
        file_put_contents($sourcePath, 'existing');
        $upload = new File($sourcePath);
        $version = new MediaVersion('thumbnail');
        $version->setUpload($upload, true);

        $processor = new VersionFileCopyProcessor(new VersionCopyStorageDriver());
        $processor->process($version);

        $this->assertSame($upload, $version->getUpload());
    }

    public function testDoesNothingWithoutOriginalVersion(): void
    {
        $version = new MediaVersion('thumbnail');

        $processor = new VersionFileCopyProcessor(new VersionCopyStorageDriver());
        $processor->process($version);

        $this->assertNull($version->getUpload());
    }

    public function testCopiesOriginalUploadToGeneratedVersion(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'media-version-original-');
        file_put_contents($sourcePath, 'original-content');

        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');
        $originalVersion->setUpload(new File($sourcePath), true);

        $version = new MediaVersion('thumbnail');
        $version->setOriginalVersion($originalVersion);

        $processor = new VersionFileCopyProcessor(new VersionCopyStorageDriver());
        $processor->process($version);

        $this->assertNotSame($sourcePath, $version->getUpload()->getRealPath());
        $this->assertStringEndsWith('.jpg', $version->getUpload()->getRealPath());
        $this->assertSame('original-content', file_get_contents($version->getUpload()->getRealPath()));
    }

    public function testDownloadsOriginalUrlWhenUploadIsMissing(): void
    {
        $storage = new VersionCopyStorageDriver('downloaded-content');
        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/webp');
        $originalVersion->setUrl('sfs-media-filesystem://original.webp');

        $version = new MediaVersion('thumbnail');
        $version->setOriginalVersion($originalVersion);

        $processor = new VersionFileCopyProcessor($storage);
        $processor->process($version);

        $this->assertSame('sfs-media-filesystem://original.webp', $storage->downloadedFileName);
        $this->assertStringEndsWith('.webp', $version->getUpload()->getRealPath());
        $this->assertSame('downloaded-content', file_get_contents($version->getUpload()->getRealPath()));
    }
}

class VersionCopyStorageDriver implements StorageDriverInterface
{
    public ?string $downloadedFileName = null;

    public function __construct(private readonly string $downloadContent = '')
    {
    }

    public function store(File $file, string $destName): string
    {
        return $destName;
    }

    public function remove(string $fileName): void
    {
    }

    public function download(string $fileName, string $destPath): void
    {
        $this->downloadedFileName = $fileName;
        file_put_contents($destPath, $this->downloadContent);
    }

    public function url(string $fileName): string
    {
        return $fileName;
    }
}
