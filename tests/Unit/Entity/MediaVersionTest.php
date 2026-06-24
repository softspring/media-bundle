<?php

namespace Softspring\MediaBundle\Tests\Unit\Entity;

use ReflectionClass;
use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaVersionTest extends TestCase
{
    public function testConstructorWithArguments(): void
    {
        $version = new MediaVersion();
        $this->assertNull($version->getVersion());
        $this->assertNull($version->getMedia());

        $version = new MediaVersion('small');
        $this->assertEquals('small', $version->getVersion());
        $this->assertNull($version->getMedia());

        $media = new Media();
        $version = new MediaVersion('small', $media);
        $this->assertEquals('small', $version->getVersion());
        $this->assertEquals($media, $version->getMedia());
        $this->assertEquals(1, $media->getVersions()->count());
    }

    public function testUrl(): void
    {
        $version = new MediaVersion();

        $this->assertNull($version->getUrl());
        $this->assertNull($version->getPublicUrl());

        $version->setUrl('https://example.com/image.jpg');
        $this->assertEquals('https://example.com/image.jpg', $version->getUrl());
        $this->assertSame('https://example.com/image.jpg', $version->getPublicUrl());

        $version->setUrl('gs://bucket/path/image.jpg');
        $this->assertSame('https://storage.googleapis.com/bucket/path/image.jpg', $version->getPublicUrl());

        $version->setUrl('sfs-media-filesystem://path/image.jpg');
        $this->assertSame('/media/path/image.jpg', $version->getPublicUrl());
    }

    public function testWidth(): void
    {
        $version = new MediaVersion();

        $this->assertNull($version->getWidth());

        $version->setWidth(300);
        $this->assertEquals(300, $version->getWidth());
    }

    public function testHeight(): void
    {
        $version = new MediaVersion();

        $this->assertNull($version->getHeight());

        $version->setHeight(300);
        $this->assertEquals(300, $version->getHeight());
    }

    public function testFileSize(): void
    {
        $version = new MediaVersion();

        $this->assertNull($version->getFileSize());

        $version->setFileSize(100);
        $this->assertEquals(100, $version->getFileSize());
    }

    public function testFileMimeType(): void
    {
        $version = new MediaVersion();

        $this->assertNull($version->getFileMimeType());

        $version->setFileMimeType('image/jpeg');
        $this->assertEquals('image/jpeg', $version->getFileMimeType());
        $this->assertTrue($version->isImageFile());
        $this->assertFalse($version->isVideoFile());

        $version->setFileMimeType('video/mp4');
        $this->assertTrue($version->isVideoFile());
        $this->assertFalse($version->isImageFile());
    }

    public function testOptions(): void
    {
        $version = new MediaVersion();

        $this->assertNull($version->getOptions());

        $version->setOptions(['option1' => 'value1']);
        $this->assertEquals(['option1' => 'value1'], $version->getOptions());
    }

    public function testOriginalVersion(): void
    {
        $version = new MediaVersion();

        $this->assertNull($version->getOriginalVersion());

        $version->setOriginalVersion($originalVersion = new MediaVersion());
        $this->assertEquals($originalVersion, $version->getOriginalVersion());
    }

    public function testUpload(): void
    {
        $version = new MediaVersion();
        $media = new Media();
        $version->setMedia($media);

        $this->assertNull($version->getUpload());
        $this->assertNull($version->getUploadedAt());
        $this->assertNull($version->getGeneratedAt());

        $version->setUpload($file = new UploadedFile('tests/example.png', 'test', null, null, true));
        $this->assertEquals($file, $version->getUpload());
        $this->assertEquals(date('H:i:s d-m-Y'), $version->getUploadedAt()->format('H:i:s d-m-Y'));
        $this->assertNull($version->getGeneratedAt());
    }

    public function testGenerated(): void
    {
        $version = new MediaVersion();
        $media = new Media();
        $version->setMedia($media);

        $this->assertNull($version->getUpload());
        $this->assertNull($version->getUploadedAt());
        $this->assertNull($version->getGeneratedAt());

        $version->setUpload($file = new File('example', false));
        $this->assertEquals($file, $version->getUpload());
        $this->assertNull($version->getUploadedAt());
        $this->assertEquals(date('H:i:s d-m-Y'), $version->getGeneratedAt()->format('H:i:s d-m-Y'));
    }

    public function testKeepTmpFileFlag(): void
    {
        $version = new MediaVersion();
        $this->assertFalse($version->isKeepTmpFile());

        $version->setUpload(new File('example', false), true);

        $this->assertTrue($version->isKeepTmpFile());
    }

    public function testOriginalSha1IsPropagatedToMedia(): void
    {
        $media = new Media();
        $originalVersion = new MediaVersion('_original', $media);
        $thumbnailVersion = new MediaVersion('thumbnail', $media);

        $originalVersion->setSha1('original-sha');
        $thumbnailVersion->setSha1('thumbnail-sha');

        $this->assertSame('original-sha', $originalVersion->getSha1());
        $this->assertSame('thumbnail-sha', $thumbnailVersion->getSha1());
        $this->assertSame('original-sha', $media->getSha1());
    }

    public function testId(): void
    {
        $version = new MediaVersion();
        $this->assertNull($version->getId());
        $this->assertEquals('', "$version");

        // write protected property
        $reflectionClass = new ReflectionClass($version);
        $reflectionProperty = $reflectionClass->getProperty('id');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($version, 'id1');

        $this->assertEquals('id1', $version->getId());
        $this->assertEquals('id1', "$version");
    }
}
