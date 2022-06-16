<?php

namespace Softspring\MediaBundle\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Model\MediaInterface;

class MediaTest extends TestCase
{
    public function testMediaType()
    {
        $media = new Media();

        $this->assertNull($media->getMediaType());
        $this->assertFalse($media->isVideo());
        $this->assertFalse($media->isImage());

        $media->setMediaType(MediaInterface::MEDIA_TYPE_IMAGE);
        $this->assertEquals(MediaInterface::MEDIA_TYPE_IMAGE, $media->getMediaType());
        $this->assertFalse($media->isVideo());
        $this->assertTrue($media->isImage());

        $media->setMediaType(MediaInterface::MEDIA_TYPE_VIDEO);
        $this->assertEquals(MediaInterface::MEDIA_TYPE_VIDEO, $media->getMediaType());
        $this->assertTrue($media->isVideo());
        $this->assertFalse($media->isImage());
    }

    public function testType()
    {
        $media = new Media();

        $this->assertNull($media->getType());

        $media->setType('test');
        $this->assertEquals('test', $media->getType());
    }

    public function testName()
    {
        $media = new Media();

        $this->assertNull($media->getName());

        $media->setName('Test media name');
        $this->assertEquals('Test media name', $media->getName());
    }

    public function testDescription()
    {
        $media = new Media();

        $this->assertNull($media->getDescription());

        $media->setDescription('Test media description');
        $this->assertEquals('Test media description', $media->getDescription());
    }

    public function testVersions()
    {
        $media = new Media();

        $this->assertEquals(0, $media->getVersions()->count());

        // test add version
        $version1 = new MediaVersion();
        $version1->setVersion('big');
        $media->addVersion($version1);
        $this->assertEquals($media, $version1->getMedia());
        $this->assertEquals(1, $media->getVersions()->count());

        $version2 = new MediaVersion();
        $version2->setVersion('small');
        $media->addVersion($version2);
        $this->assertEquals($media, $version2->getMedia());
        $this->assertEquals(2, $media->getVersions()->count());

        // test get version
        $this->assertEquals($version1, $media->getVersion('big'));
        $this->assertEquals($version2, $media->getVersion('small'));
        $this->assertNull($media->getVersion('other'));

        // test remove version
        $media->removeVersion($version1);
        $this->assertEquals(1, $media->getVersions()->count());
    }

    public function testId()
    {
        $media = new Media();
        $this->assertNull($media->getId());
        $this->assertEquals('', "$media");

        // write protected property
        $reflectionClass = new \ReflectionClass($media);
        $reflectionProperty = $reflectionClass->getProperty('id');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($media, 'id1');

        $this->assertEquals('id1', $media->getId());
        $this->assertEquals('id1', "$media");
    }
}
