<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Twig\Extension;

use Exception;
use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Render\MediaRenderer;
use Softspring\MediaBundle\Twig\Extension\RenderMediaExtension;
use Softspring\MediaBundle\Type\ConfigMediaTypeProvider;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use stdClass;
use Twig\TwigFilter;
use Twig\TwigFunction;

class RenderMediaExtensionTest extends TestCase
{
    private const TYPES = [
        'image' => [
            'type' => 'image',
        ],
    ];

    public function testRegistersFiltersAndFunctions(): void
    {
        $extension = new RenderMediaExtension(
            $this->createMock(MediaRenderer::class),
            new MediaTypesCollection([new ConfigMediaTypeProvider(self::TYPES)])
        );

        $this->assertSame([
            'sfs_media_render_video',
            'sfs_media_render_video_set',
            'sfs_media_render_image',
            'sfs_media_render_picture',
            'sfs_media_render',
            'sfs_media_image_url',
            'sfs_media_version_url',
            'sfs_media_type_config',
        ], array_map(static fn (TwigFilter $filter): string => $filter->getName(), $extension->getFilters()));

        $this->assertSame([
            'sfs_media_render_video',
            'sfs_media_render_video_set',
            'sfs_media_render_image',
            'sfs_media_render_picture',
            'sfs_media_render',
            'sfs_media_image_url',
            'sfs_media_version_url',
            'sfs_media_type_config',
        ], array_map(static fn (TwigFunction $function): string => $function->getName(), $extension->getFunctions()));
    }

    public function testReturnsMediaTypeConfigFromStringOrMedia(): void
    {
        $extension = new RenderMediaExtension(
            $this->createMock(MediaRenderer::class),
            new MediaTypesCollection([new ConfigMediaTypeProvider(self::TYPES)])
        );
        $media = new Media();
        $media->setType('image');

        $this->assertSame(self::TYPES['image'], $extension->getMediaTypeConfig('image'));
        $this->assertSame(self::TYPES['image'], $extension->getMediaTypeConfig($media));
        $this->assertNull($extension->getMediaTypeConfig('missing'));

        $media->setType('missing');
        $this->assertNull($extension->getMediaTypeConfig($media));
    }

    public function testRejectsInvalidMediaTypeConfigInput(): void
    {
        $extension = new RenderMediaExtension(
            $this->createMock(MediaRenderer::class),
            new MediaTypesCollection([new ConfigMediaTypeProvider(self::TYPES)])
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('sfs_media_type_config parameter can be a string or a MediaInterface');

        $extension->getMediaTypeConfig(new stdClass());
    }
}
