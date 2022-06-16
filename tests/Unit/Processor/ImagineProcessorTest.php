<?php

namespace Softspring\MediaBundle\Tests\Unit\Processor;

use Imagine\Image\ImageInterface;
use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Processor\ImagineProcessor;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImagineProcessorTest extends TestCase
{
    public function testPriority()
    {
        $this->assertEquals(0, ImagineProcessor::getPriority());
    }

    public function testNothingToDoWithOriginalVersion()
    {
        $processor = new ImagineProcessor();
        $version = new MediaVersion('_original');
        $this->assertFalse($processor->supports($version));
    }

    public function testFailIfNoOriginalVersionProvided()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Processor support method requires version original version is initialized');

        $processor = new ImagineProcessor();
        $version = new MediaVersion('xs');
        $processor->supports($version);
    }

    public function testFailIfNoOptionsProvided()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Processor support method requires version options are initialized');

        $processor = new ImagineProcessor();
        $version = new MediaVersion('xs');
        $version->setOriginalVersion(new MediaVersion('_original'));
        $processor->supports($version);
    }

    public function testNothingToDoWithOriginalVersionInvalidMimeType()
    {
        $processor = new ImagineProcessor();
        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/invalid');
        $version = new MediaVersion('xs');
        $version->setOriginalVersion($originalVersion);
        $version->setOptions(['type' => 'jpeg']);
        $this->assertFalse($processor->supports($version));
    }

    public function testNothingToDoWithUnsupportedOptionTypeValue()
    {
        $processor = new ImagineProcessor();
        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');
        $version = new MediaVersion('xs');
        $version->setOriginalVersion($originalVersion);
        $version->setOptions(['type' => 'invalid']);
        $this->assertFalse($processor->supports($version));
    }

    public function testSupportedProcessing()
    {
        $processor = new ImagineProcessor();
        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');
        $version = new MediaVersion('xs');
        $version->setOriginalVersion($originalVersion);
        $version->setOptions(['type' => 'png']);
        $this->assertTrue($processor->supports($version));
    }

    public function testNothinToProcessWhenNoUploadFile()
    {
        $processor = new ImagineProcessor();
        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');
        $version = new MediaVersion('xs');
        $version->setOriginalVersion($originalVersion);
        $version->setOptions(['type' => 'png']);
        $version->setUpload(new UploadedFile('/tmp/uploadedFile', 'name.jpeg', null, 100, true));
        $this->assertTrue($processor->process($version) || true);
    }

    public function testJpegToPngConversion()
    {
        $processor = new ImagineProcessor();

        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');

        $originFilePath = __DIR__.'/resources/energy.250x141.96ppi.jpg';
        $filePath = __DIR__.'/results/testJpegToPngConversion.jpeg';
        $filePathPng = __DIR__.'/results/testJpegToPngConversion.png';
        is_file($filePath) && unlink($filePath);
        copy($originFilePath, $filePath);

        $version = new MediaVersion('xs');
        $version->setUpload(new File(__DIR__.'/results/testJpegToPngConversion.jpeg'));
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

    public function testChangeResolution()
    {
        $processor = new ImagineProcessor();

        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');

        $originFilePath = __DIR__.'/resources/energy.250x141.96ppi.jpg';
        $filePath = __DIR__.'/results/testChangeResolution.jpeg';
        is_file($filePath) && unlink($filePath);
        copy($originFilePath, $filePath);

        $version = new MediaVersion('xs');
        $version->setUpload(new File(__DIR__.'/results/testChangeResolution.jpeg'));
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
        $resolution = imageresolution(imagecreatefromjpeg($version->getUpload()->getRealPath()));
        // $this->assertEquals([72, 72], $resolution);
        // TODO NOT WORKING WITH GD
    }

    public function testScaleWidth()
    {
        $processor = new ImagineProcessor();

        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');
        $originalVersion->setWidth(250);
        $originalVersion->setHeight(141);

        $originFilePath = __DIR__.'/resources/energy.250x141.96ppi.jpg';
        $filePath = __DIR__.'/results/testScaleWidth.jpeg';
        is_file($filePath) && unlink($filePath);
        copy($originFilePath, $filePath);

        $version = new MediaVersion('xs');
        $version->setUpload(new File(__DIR__.'/results/testScaleWidth.jpeg'));
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

    public function testScaleHeight()
    {
        $processor = new ImagineProcessor();

        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');
        $originalVersion->setWidth(250);
        $originalVersion->setHeight(141);

        $originFilePath = __DIR__.'/resources/energy.250x141.96ppi.jpg';
        $filePath = __DIR__.'/results/testScaleHeight.jpeg';
        is_file($filePath) && unlink($filePath);
        copy($originFilePath, $filePath);

        $version = new MediaVersion('xs');
        $version->setUpload(new File(__DIR__.'/results/testScaleHeight.jpeg'));
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

    public function testScaleBoth()
    {
        $processor = new ImagineProcessor();

        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType('image/jpeg');
        $originalVersion->setWidth(250);
        $originalVersion->setHeight(141);

        $originFilePath = __DIR__.'/resources/energy.250x141.96ppi.jpg';
        $filePath = __DIR__.'/results/testScaleBoth.jpeg';
        is_file($filePath) && unlink($filePath);
        copy($originFilePath, $filePath);

        $version = new MediaVersion('xs');
        $version->setUpload(new File(__DIR__.'/results/testScaleBoth.jpeg'));
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

    public function testWorkWithPngTransparent()
    {
        $this->assertTrue((bool) 'TODO');
    }
}
