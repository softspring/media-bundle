<?php

namespace Softspring\MediaBundle\Tests\Unit\Render;

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
        ],
    ];

    public function testRenderImages()
    {
        $storageDriver = new FilesystemStorageDriver('path', 'url');
        $renderer = new MediaRenderer(new MediaTypesCollection([new ConfigMediaTypeProvider(self::TYPES)]), $storageDriver);

        $media = new Media();
        $media->setMediaType(MediaInterface::MEDIA_TYPE_IMAGE);
        $media->setType('background');

        $versionXl = new MediaVersion('xl', $media);
        $versionXl->setUrl('https://example.com/image.xl.jpeg');
        $versionXl->setWidth(1800);
        $versionXl->setHeight(1600);

        $expectedXlImg = '<img width="1800" height="1600" class="img-fluid" src="https://example.com/image.xl.jpeg" alt="" />';
        $this->assertEquals($expectedXlImg, $renderer->renderImage($media, 'xl', ['class' => 'img-fluid']));

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

    public function testPictureException()
    {
        $storageDriver = new FilesystemStorageDriver('path', 'url');
        $renderer = new MediaRenderer(new MediaTypesCollection([new ConfigMediaTypeProvider(self::TYPES)]), $storageDriver);

        $media = new Media();
        $media->setMediaType(MediaInterface::MEDIA_TYPE_IMAGE);
        $media->setType('background');

        $this->expectException(\Exception::class);
        $renderer->renderPicture($media, 'bad_picture_not_in_config');
    }
}
