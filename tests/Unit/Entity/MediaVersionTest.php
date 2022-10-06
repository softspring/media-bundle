<?php

namespace Softspring\MediaBundle\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaVersionTest extends TestCase
{
    public function testConstructorWithArguments()
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

    public function testUrl()
    {
        $version = new MediaVersion();

        $this->assertNull($version->getUrl());

        $version->setUrl('https://example.com/image.jpg');
        $this->assertEquals('https://example.com/image.jpg', $version->getUrl());
    }

    public function testWidth()
    {
        $version = new MediaVersion();

        $this->assertNull($version->getWidth());

        $version->setWidth(300);
        $this->assertEquals(300, $version->getWidth());
    }

    public function testHeight()
    {
        $version = new MediaVersion();

        $this->assertNull($version->getHeight());

        $version->setHeight(300);
        $this->assertEquals(300, $version->getHeight());
    }

    public function testFileSize()
    {
        $version = new MediaVersion();

        $this->assertNull($version->getFileSize());

        $version->setFileSize(100);
        $this->assertEquals(100, $version->getFileSize());
    }

    public function testFileMimeType()
    {
        $version = new MediaVersion();

        $this->assertNull($version->getFileMimeType());

        $version->setFileMimeType('image/jpeg');
        $this->assertEquals('image/jpeg', $version->getFileMimeType());
    }

    public function testOptions()
    {
        $version = new MediaVersion();

        $this->assertNull($version->getOptions());

        $version->setOptions(['option1' => 'value1']);
        $this->assertEquals(['option1' => 'value1'], $version->getOptions());
    }

    public function testOriginalVersion()
    {
        $version = new MediaVersion();

        $this->assertNull($version->getOriginalVersion());

        $version->setOriginalVersion($originalVersion = new MediaVersion());
        $this->assertEquals($originalVersion, $version->getOriginalVersion());
    }

    public function testUpload()
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

    public function testGenerated()
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

    public function testId()
    {
        $version = new MediaVersion();
        $this->assertNull($version->getId());
        $this->assertEquals('', "$version");

        // write protected property
        $reflectionClass = new \ReflectionClass($version);
        $reflectionProperty = $reflectionClass->getProperty('id');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($version, 'id1');

        $this->assertEquals('id1', $version->getId());
        $this->assertEquals('id1', "$version");
    }
}
