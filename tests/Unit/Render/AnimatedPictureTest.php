<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Render;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Render\MediaRenderer;
use Softspring\MediaBundle\Storage\StorageDriverInterface;
use Softspring\MediaBundle\Type\ConfigMediaTypeProvider;
use Softspring\MediaBundle\Type\MediaTypesCollection;

final class AnimatedPictureTest extends TestCase
{
    public function testAnimationSkipsStaticSourceAndUsesAvifImg(): void
    {
        $html = $this->render(true);
        self::assertStringNotContainsString('still.jpg', $html);
        self::assertStringContainsString('srcset="https://media.example/animated.avif 1x"', $html);
        self::assertStringContainsString('src="https://media.example/animated.avif"', $html);
        self::assertStringNotContainsString('skip_if_animated', $html);
    }

    public function testStaticImageRetainsAllSourcesAndUsesAvifImg(): void
    {
        $html = $this->render(false);
        self::assertStringContainsString('srcset="https://media.example/still.jpg 1x"', $html);
        self::assertStringContainsString('src="https://media.example/animated.avif"', $html);
    }

    public function testSkippingSourcesIsOptIn(): void
    {
        self::assertStringContainsString('srcset="https://media.example/still.jpg 1x"', $this->render(true, false));
    }

    private function render(bool $animated, bool $skip = true): string
    {
        $media = new Media();
        $media->setType('content');
        $media->setMetadataField('animated', $animated);
        foreach (['still' => 'still.jpg', 'moving' => 'animated.avif'] as $name => $file) {
            $version = new MediaVersion($name, $media);
            $version->setUrl('https://media.example/'.$file);
            $version->setWidth(400);
            $version->setHeight(200);
        }
        $types = new MediaTypesCollection([new ConfigMediaTypeProvider(['content' => [
            'pictures' => ['_default' => [
                'sources' => [
                    ['skip_if_animated' => $skip, 'srcset' => [['version' => 'still', 'suffix' => '1x']], 'attrs' => ['type' => 'image/jpeg']],
                    ['srcset' => [['version' => 'moving', 'suffix' => '1x']], 'attrs' => ['type' => 'image/avif']],
                ],
                'img' => ['src_version' => 'moving'],
            ]],
        ]])]);
        $storage = $this->createMock(StorageDriverInterface::class);
        $storage->expects(self::never())->method('download');
        $renderer = new MediaRenderer($types, $storage, $this->createMock(EntityManagerInterface::class));

        return $renderer->renderPicture($media);
    }
}
