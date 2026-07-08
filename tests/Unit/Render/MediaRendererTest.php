<?php

namespace Softspring\MediaBundle\Tests\Unit\Render;

use Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Render\MediaRenderer;
use Softspring\MediaBundle\Storage\FilesystemStorageDriver;
use Softspring\MediaBundle\Type\ConfigMediaTypeProvider;
use Softspring\MediaBundle\Type\MediaTypesCollection;

class MediaRendererTest extends TestCase
{
    public const TYPES = [
        'background' => [
            'versions' => [
                'xl' => [],
                'l' => [],
                's' => [],
            ],
            'pictures' => [
                '_default' => [
                    'sources' => [
                        [
                            'srcset' => [
                                ['version' => 'l', 'suffix' => '1x'],
                                ['version' => 'xl', 'suffix' => '2x'],
                            ],
                            'attrs' => [
                                'media' => '(min-width: 200w)',
                            ],
                        ],
                        [
                            'srcset' => [
                                ['version' => 's', 'suffix' => null],
                            ],
                            'attrs' => [
                                'media' => '(min-width: 200w)',
                            ],
                        ],
                    ],
                    'img' => [
                        'src_version' => 'xl',
                    ],
                ],
            ],
            'video_sets' => [
                '_default' => [
                    'attrs' => [
                        'preload' => 'metadata',
                    ],
                    'poster_version' => 'poster',
                    'sources' => [
                        [
                            'version' => 'mp4',
                            'attrs' => [
                                'media' => '(min-width: 600px)',
                            ],
                        ],
                        [
                            'version' => 'missing',
                        ],
                    ],
                ],
            ],
        ],
    ];

    public function testRenderImages(): void
    {
        $storageDriver = new FilesystemStorageDriver('path', 'url');
        $em = $this->createMock(EntityManagerInterface::class);
        $renderer = new MediaRenderer(new MediaTypesCollection([new ConfigMediaTypeProvider(self::TYPES)]), $storageDriver, $em);

        $media = new Media();
        $media->setMediaType(MediaInterface::MEDIA_TYPE_IMAGE);
        $media->setType('background');

        $versionXl = new MediaVersion('xl', $media);
        $versionXl->setUrl('https://example.com/image.xl.jpeg');
        $versionXl->setWidth(1800);
        $versionXl->setHeight(1600);

        $expectedXlImg = '<img width="1800" height="1600" class="img-fluid" src="https://example.com/image.xl.jpeg" alt="" />';
        $this->assertEquals($expectedXlImg, $renderer->renderImage($media, 'xl', ['class' => 'img-fluid']));

        $media->setDescription('A "quoted" image & <tag>');
        $expectedXlImg = '<img width="1800" height="1600" class="img-fluid" src="https://example.com/image.xl.jpeg" alt="A &quot;quoted&quot; image &amp; &lt;tag&gt;" />';
        $this->assertEquals($expectedXlImg, $renderer->renderImage($media, 'xl', ['class' => 'img-fluid']));
        $media->setDescription(null);

        $versionL = new MediaVersion('l', $media);
        $versionL->setUrl('https://example.com/image.l.jpeg');
        $versionL->setWidth(800);
        $versionL->setHeight(600);

        $expectedLImg = '<img width="800" height="600" class="img-fluid" src="https://example.com/image.l.jpeg" alt="" />';
        $this->assertEquals($expectedLImg, $renderer->renderImage($media, 'l', ['class' => 'img-fluid']));

        $expectedLImg = '';
        $this->assertEquals($expectedLImg, $renderer->renderImage($media, 'bad', ['class' => 'img-fluid']));

        $expectedLImg = '';
        $this->assertEquals($expectedLImg, $renderer->renderImage($media, ['bad', 'bad2'], ['class' => 'img-fluid']));

        $expectedLImg = '<img width="800" height="600" class="img-fluid" src="https://example.com/image.l.jpeg" alt="" />';
        $this->assertEquals($expectedLImg, $renderer->renderImage($media, ['bad', 'l'], ['class' => 'img-fluid']));

        $versionS = new MediaVersion('s', $media);
        $versionS->setUrl('image.s.jpeg');
        $versionS->setWidth(800);
        $versionS->setHeight(600);

        $expectedLImg = '<img width="800" height="600" class="img-fluid" src="image.s.jpeg" alt="" />';
        $this->assertEquals($expectedLImg, $renderer->renderImage($media, 's', ['class' => 'img-fluid']));

        $expectedPicture = '<picture class="img-fluid"><source media="(min-width: 200w)" srcset="https://example.com/image.l.jpeg 1x, https://example.com/image.xl.jpeg 2x" /><source media="(min-width: 200w)" srcset="image.s.jpeg" /><img width="1800" height="1600" data-example="1" src="https://example.com/image.xl.jpeg" alt="" /></picture>';
        $this->assertEquals($expectedPicture, $renderer->renderPicture($media, '_default', ['class' => 'img-fluid'], ['data-example' => true]));
    }

    public function testPictureException(): void
    {
        $storageDriver = new FilesystemStorageDriver('path', 'url');
        $em = $this->createMock(EntityManagerInterface::class);
        $renderer = new MediaRenderer(new MediaTypesCollection([new ConfigMediaTypeProvider(self::TYPES)]), $storageDriver, $em);

        $media = new Media();
        $media->setMediaType(MediaInterface::MEDIA_TYPE_IMAGE);
        $media->setType('background');

        $this->expectException(Exception::class);
        $renderer->renderPicture($media, 'bad_picture_not_in_config');
    }

    public function testRenderMediaDispatcherAndArrays(): void
    {
        $renderer = $this->createRenderer();
        $media = $this->createMediaWithImageVersions();

        $expected = '<img width="1800" height="1600" src="https://example.com/image.xl.jpeg" alt="" />';

        $this->assertSame($expected, $renderer->render($media, 'image#xl'));
        $this->assertSame($expected, $renderer->renderMediaOrArray($media, 'image#xl'));
        $this->assertSame($expected, $renderer->renderMediaOrArray(['media' => $media, 'version' => 'image#xl']));
        $this->assertSame('', $renderer->render(null, 'image#xl'));
        $this->assertSame('', $renderer->render($media, null));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid $versionString');
        $renderer->render($media, 'invalid#xl');
    }

    public function testImageUrlReturnsFirstAvailableVersion(): void
    {
        $renderer = $this->createRenderer();
        $media = $this->createMediaWithImageVersions();

        $this->assertSame('https://example.com/image.xl.jpeg', $renderer->imageUrl($media, ['missing', 'xl']));
        $this->assertSame('', $renderer->imageUrl($media, ['missing']));
        $this->assertSame('', $renderer->imageUrl($media, 'missing'));
    }

    public function testRenderVideos(): void
    {
        $renderer = $this->createRenderer();
        $media = $this->createMediaWithVideoVersions();

        $this->assertSame(
            '<video class="player" controls="" src="https://example.com/video.mp4" />',
            $renderer->renderVideo($media, 'mp4', ['class' => 'player', 'controls' => true])
        );
        $this->assertSame(
            '<video class="player" src="https://example.com/video.mp4" />',
            $renderer->renderVideo($media, 'mp4', ['class' => 'player', 'controls' => false])
        );
        $this->assertSame(
            '<img width="640" height="360" class="poster" src="https://example.com/poster.jpg" alt="Poster" />',
            $renderer->renderVideo($media, 'poster', ['class' => 'poster'])
        );
        $this->assertSame(
            '<video preload="metadata" controls="1" poster="https://example.com/poster.jpg"><source media="(min-width: 600px)" src="https://example.com/video.mp4" type="video/mp4" /></video>',
            $renderer->renderVideoWithSources($media, '_default', ['controls' => true])
        );
        $this->assertSame('', $renderer->renderVideo($media, ['missing']));
        $this->assertSame('<video class="player" controls="" src="https://example.com/video.mp4" />', $renderer->renderVideo($media, ['missing', 'mp4'], ['class' => 'player', 'controls' => true]));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('video_sets config is not set for background');
        $renderer->renderVideoWithSources($media, 'missing');
    }

    public function testResolvesMediaById(): void
    {
        $media = $this->createMediaWithImageVersions();
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => 'media-id'])
            ->willReturn($media);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('getRepository')
            ->with(Media::class)
            ->willReturn($repository);

        $renderer = new MediaRenderer(new MediaTypesCollection([new ConfigMediaTypeProvider(self::TYPES)]), new FilesystemStorageDriver('path', 'url'), $em);

        $this->assertSame('https://example.com/image.xl.jpeg', $renderer->imageUrl('media-id', 'xl'));
    }

    private function createRenderer(): MediaRenderer
    {
        return new MediaRenderer(
            new MediaTypesCollection([new ConfigMediaTypeProvider(self::TYPES)]),
            new FilesystemStorageDriver('path', 'url'),
            $this->createMock(EntityManagerInterface::class),
        );
    }

    private function createMediaWithImageVersions(): Media
    {
        $media = new Media();
        $media->setMediaType(MediaInterface::MEDIA_TYPE_IMAGE);
        $media->setType('background');

        $versionXl = new MediaVersion('xl', $media);
        $versionXl->setUrl('https://example.com/image.xl.jpeg');
        $versionXl->setWidth(1800);
        $versionXl->setHeight(1600);

        return $media;
    }

    private function createMediaWithVideoVersions(): Media
    {
        $media = $this->createMediaWithImageVersions();
        $media->setName('Video');

        $poster = new MediaVersion('poster', $media);
        $poster->setUrl('https://example.com/poster.jpg');
        $poster->setFileMimeType('image/jpeg');
        $poster->setWidth(640);
        $poster->setHeight(360);
        $poster->getMedia()->setName('Poster');

        $mp4 = new MediaVersion('mp4', $media);
        $mp4->setUrl('https://example.com/video.mp4');
        $mp4->setFileMimeType('video/mp4');

        return $media;
    }
}
