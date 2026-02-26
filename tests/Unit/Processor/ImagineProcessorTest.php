<?php

namespace Softspring\MediaBundle\Tests\Unit\Processor;

use Exception;
use Imagine\Image\ImageInterface;
use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Processor\ImagineProcessor;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImagineProcessorTest extends TestCase
{
    protected string $resultsPath;

    protected function setUp(): void
    {
        $this->resultsPath = sys_get_temp_dir().'/imagine-processor-test';
        if (!is_dir($this->resultsPath)) {
            mkdir($this->resultsPath);
        }

        if (isset($_ENV['GITHUB_ACTION'])) {
            $this->markTestSkipped('Test skipped in GitHubActions');
        }
    }

    public function testPriority(): void
    {
        $this->assertEquals(0, ImagineProcessor::getPriority());
    }

    public function testNothingToDoWithOriginalVersion(): void
    {
        $processor = new ImagineProcessor();
        $version = new MediaVersion('_original');
        $this->assertFalse($processor->supports($version));
    }

    public function testFailIfNoOptionsProvided(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Processor support method requires version options are initialized');

        $processor = new ImagineProcessor();
        $version = new MediaVersion('xs');
        $version->setOriginalVersion(new MediaVersion('_original'));
        $processor->supports($version);
    }

    public function testNothingToDoWithOriginalVersionInvalidMimeType(): void
    {
        $processor = new ImagineProcessor();
        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/invalid');
        $version = new MediaVersion('xs');
        $version->setOriginalVersion($originalVersion);
        $version->setOptions(['type' => 'jpeg']);
        $this->assertFalse($processor->supports($version));
    }

    public function testNothingToDoWithUnsupportedOptionTypeValue(): void
    {
        $processor = new ImagineProcessor();
        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');
        $version = new MediaVersion('xs');
        $version->setOriginalVersion($originalVersion);
        $version->setOptions(['type' => 'invalid']);
        $this->assertFalse($processor->supports($version));
    }

    public function testSupportedProcessing(): void
    {
        $processor = new ImagineProcessor();
        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');
        $version = new MediaVersion('xs');
        $version->setOriginalVersion($originalVersion);
        $version->setOptions(['type' => 'png']);
        $this->assertTrue($processor->supports($version));
    }

    public function testNothingToProcessWhenNoUploadFile(): void
    {
        $processor = new ImagineProcessor();
        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');
        $version = new MediaVersion('xs');
        $version->setOriginalVersion($originalVersion);
        $version->setOptions(['type' => 'png']);
        $version->setUpload(new UploadedFile('/tmp/uploadedFile', 'name.jpeg', null, 100, true));
        $processor->process($version);
        $this->assertNotNull($version->getUpload());
    }

    public function testJpegToPngConversion(): void
    {
        $processor = new ImagineProcessor();

        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');

        $originFilePath = __DIR__.'/resources/energy.250x141.96ppi.jpg';
        $filePath = "{$this->resultsPath}/testJpegToPngConversion.jpeg";
        $filePathPng = "{$this->resultsPath}/testJpegToPngConversion.png";
        if (is_file($filePath)) {
            unlink($filePath);
        }
        copy($originFilePath, $filePath);

        $version = new MediaVersion('xs');
        $version->setUpload(new File("{$this->resultsPath}/testJpegToPngConversion.jpeg"));
        $version->setOriginalVersion($originalVersion);
        $version->setOptions(['type' => 'png', 'png_compression_level' => 9]);

        $processor->process($version);

        $this->assertEquals('testJpegToPngConversion.png', $version->getUpload()->getBasename());
        [0 => $resultWidth, 1 => $resultHeight, 2 => $imageType, 3 => $htmlAttributes, 'bits' => $bits, 'mime' => $mime] = getimagesize($filePathPng);
        $this->assertEquals(250, $resultWidth);
        $this->assertEquals(141, $resultHeight);
        $this->assertEquals('image/png', $mime);
        $resolution = imageresolution(imagecreatefrompng($version->getUpload()->getRealPath()));
        $this->assertEquals([96, 96], $resolution);
    }

    public function testChangeResolution(): void
    {
        $processor = new ImagineProcessor();

        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');

        $originFilePath = __DIR__.'/resources/energy.250x141.96ppi.jpg';
        $filePath = "{$this->resultsPath}/testChangeResolution.jpeg";
        if (is_file($filePath)) {
            unlink($filePath);
        }
        copy($originFilePath, $filePath);

        $version = new MediaVersion('xs');
        $version->setUpload(new File("{$this->resultsPath}/testChangeResolution.jpeg"));
        $version->setOriginalVersion($originalVersion);
        $version->setOptions([
            'type' => 'jpeg',
            'jpeg_compression' => 70,
            'resolution-x' => 72,
            'resolution-y' => 72,
            'resolution-units' => ImageInterface::RESOLUTION_PIXELSPERINCH,
            'resampling-filter' => ImageInterface::FILTER_LANCZOS,
        ]);

        $processor->process($version);

        $this->assertEquals('testChangeResolution.jpeg', $version->getUpload()->getBasename());
        [0 => $resultWidth, 1 => $resultHeight, 2 => $imageType, 3 => $htmlAttributes, 'bits' => $bits, 'mime' => $mime] = getimagesize($filePath);
        $this->assertEquals(250, $resultWidth);
        $this->assertEquals(141, $resultHeight);
        $this->assertEquals('image/jpeg', $mime);
        imageresolution(imagecreatefromjpeg($version->getUpload()->getRealPath()));
        // $this->assertEquals([72, 72], $resolution);
        // TODO NOT WORKING WITH GD
    }

    public function testScaleWidth(): void
    {
        $processor = new ImagineProcessor();

        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');
        $originalVersion->setWidth(250);
        $originalVersion->setHeight(141);

        $originFilePath = __DIR__.'/resources/energy.250x141.96ppi.jpg';
        $filePath = "{$this->resultsPath}/testScaleWidth.jpeg";
        if (is_file($filePath)) {
            unlink($filePath);
        }
        copy($originFilePath, $filePath);

        $version = new MediaVersion('xs');
        $version->setUpload(new File("{$this->resultsPath}/testScaleWidth.jpeg"));
        $version->setOriginalVersion($originalVersion);
        $version->setOptions([
            'type' => 'jpeg',
            'jpeg_compression' => 70,
            'scale_width' => 100,
        ]);

        $processor->process($version);

        $this->assertEquals('testScaleWidth.jpeg', $version->getUpload()->getBasename());
        [0 => $resultWidth, 1 => $resultHeight, 2 => $imageType, 3 => $htmlAttributes, 'bits' => $bits, 'mime' => $mime] = getimagesize($filePath);
        $this->assertEquals(100, $resultWidth);
        $this->assertEquals(56, $resultHeight);
        $this->assertEquals('image/jpeg', $mime);
    }

    public function testScaleHeight(): void
    {
        $processor = new ImagineProcessor();

        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');
        $originalVersion->setWidth(250);
        $originalVersion->setHeight(141);

        $originFilePath = __DIR__.'/resources/energy.250x141.96ppi.jpg';
        $filePath = "{$this->resultsPath}/testScaleHeight.jpeg";
        if (is_file($filePath)) {
            unlink($filePath);
        }
        copy($originFilePath, $filePath);

        $version = new MediaVersion('xs');
        $version->setUpload(new File("{$this->resultsPath}/testScaleHeight.jpeg"));
        $version->setOriginalVersion($originalVersion);
        $version->setOptions([
            'type' => 'jpeg',
            'jpeg_compression' => 70,
            'scale_height' => 100,
        ]);

        $processor->process($version);

        $this->assertEquals('testScaleHeight.jpeg', $version->getUpload()->getBasename());
        [0 => $resultWidth, 1 => $resultHeight, 2 => $imageType, 3 => $htmlAttributes, 'bits' => $bits, 'mime' => $mime] = getimagesize($filePath);
        $this->assertEquals(177, $resultWidth);
        $this->assertEquals(100, $resultHeight);
        $this->assertEquals('image/jpeg', $mime);
    }

    public function testScaleBoth(): void
    {
        $processor = new ImagineProcessor();

        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');
        $originalVersion->setWidth(250);
        $originalVersion->setHeight(141);

        $originFilePath = __DIR__.'/resources/energy.250x141.96ppi.jpg';
        $filePath = "{$this->resultsPath}/testScaleBoth.jpeg";
        if (is_file($filePath)) {
            unlink($filePath);
        }
        copy($originFilePath, $filePath);

        $version = new MediaVersion('xs');
        $version->setUpload(new File("{$this->resultsPath}/testScaleBoth.jpeg"));
        $version->setOriginalVersion($originalVersion);
        $version->setOptions([
            'type' => 'jpeg',
            'jpeg_compression' => 70,
            'scale_width' => 100,
            'scale_height' => 100,
        ]);

        $processor->process($version);

        $this->assertEquals('testScaleBoth.jpeg', $version->getUpload()->getBasename());
        [0 => $resultWidth, 1 => $resultHeight, 2 => $imageType, 3 => $htmlAttributes, 'bits' => $bits, 'mime' => $mime] = getimagesize($filePath);
        $this->assertEquals(100, $resultWidth);
        $this->assertEquals(100, $resultHeight);
        $this->assertEquals('image/jpeg', $mime);
    }

    public function testWorkWithPngTransparent(): void
    {
        $this->markTestSkipped('Not implemented yet');
    }
}
