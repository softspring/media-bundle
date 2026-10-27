<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Render;

use Doctrine\ORM\EntityManagerInterface;
use Google\Cloud\Storage\StorageClient;
use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Render\MediaRenderer;
use Softspring\MediaBundle\Storage\FilesystemStorageDriver;
use Softspring\MediaBundle\Storage\GoogleCloudStorageDriver;
use Softspring\MediaBundle\Type\ConfigMediaTypeProvider;
use Softspring\MediaBundle\Type\MediaTypesCollection;

final class PublicMediaUrlTest extends TestCase
{
    public function testAllRenderedUrlsUseTheConfiguredStoragePublicBase(): void
    {
        $types = new MediaTypesCollection([new ConfigMediaTypeProvider(['asset' => [
            'pictures' => ['_default' => [
                'sources' => [['srcset' => [['version' => 'image']], 'attrs' => ['type' => 'image/avif']]],
                'img' => ['src_version' => 'image'],
            ]],
            'video_sets' => ['_default' => [
                'poster_version' => 'poster',
                'sources' => [['version' => 'video']],
            ]],
        ]])]);
        $driver = new GoogleCloudStorageDriver($this->createMock(StorageClient::class), 'media-bucket', 'https://media.example.test');
        $renderer = new MediaRenderer($types, $driver, $this->createMock(EntityManagerInterface::class));
        $media = new Media();
        $media->setType('asset');
        foreach (['image' => 'avif', 'video' => 'webm', 'poster' => 'jpg'] as $name => $extension) {
            $version = new MediaVersion($name, $media);
            $version->setUrl('gs://media-bucket/path/'.$name.'.'.$extension);
            $version->setWidth(400);
            $version->setHeight(200);
        }

        self::assertSame('https://media.example.test/path/image.avif', $renderer->imageUrl($media, 'image'));
        self::assertSame('https://media.example.test/path/image.avif', $renderer->mediaVersionUrl($media->getVersion('image')));
        self::assertStringContainsString('src="https://media.example.test/path/image.avif"', $renderer->renderImage($media, 'image'));
        $picture = $renderer->renderPicture($media);
        self::assertStringContainsString('srcset="https://media.example.test/path/image.avif"', $picture);
        self::assertStringContainsString('src="https://media.example.test/path/image.avif"', $picture);
        $video = $renderer->renderVideoWithSources($media);
        self::assertStringContainsString('src="https://media.example.test/path/video.webm"', $video);
        self::assertStringContainsString('poster="https://media.example.test/path/poster.jpg"', $video);
    }

    public function testVersionsFromAnotherStorageKeepTheirPublicUrlFallback(): void
    {
        $renderer = new MediaRenderer(new MediaTypesCollection([]), new FilesystemStorageDriver('/tmp/media', '/media'), $this->createMock(EntityManagerInterface::class));
        $version = new MediaVersion('image');
        $version->setUrl('gs://media-bucket/image.avif');
        self::assertSame('https://storage.googleapis.com/media-bucket/image.avif', $renderer->mediaVersionUrl($version));
        $version->setUrl('https://external.example.test/image.avif');
        self::assertSame('https://external.example.test/image.avif', $renderer->mediaVersionUrl($version));
        self::assertNull($renderer->mediaVersionUrl(null));
    }
}
