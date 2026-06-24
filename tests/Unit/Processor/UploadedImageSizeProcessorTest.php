<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Processor\UploadedImageSizeProcessor;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadedImageSizeProcessorTest extends TestCase
{
    public function testPriority(): void
    {
        $this->assertSame(255, UploadedImageSizeProcessor::getPriority());
    }

    public function testSupportsUploadedFilesOnly(): void
    {
        $processor = new UploadedImageSizeProcessor();
        $version = new MediaVersion();

        $this->assertFalse($processor->supports($version));

        $version->setUpload(new File(__DIR__.'/../../example.png'));
        $this->assertFalse($processor->supports($version));

        $version->setUpload(new UploadedFile(__DIR__.'/../../example.png', 'example.png', null, null, true));
        $this->assertTrue($processor->supports($version));
    }

    public function testStoresImageDimensions(): void
    {
        $processor = new UploadedImageSizeProcessor();
        $version = new MediaVersion();
        $version->setUpload(new UploadedFile(__DIR__.'/../../example.png', 'example.png', null, null, true));

        $processor->process($version);

        [$width, $height] = getimagesize(__DIR__.'/../../example.png');
        $this->assertSame($width, $version->getWidth());
        $this->assertSame($height, $version->getHeight());
    }

    public function testIgnoresNonImageUploads(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'media-non-image-');
        file_put_contents($path, 'plain text');

        $processor = new UploadedImageSizeProcessor();
        $version = new MediaVersion();
        $version->setUpload(new UploadedFile($path, 'example.txt', 'text/plain', null, true));

        $processor->process($version);

        $this->assertNull($version->getWidth());
        $this->assertNull($version->getHeight());
    }
}
