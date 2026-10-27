<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

final class DefaultsConfigurationTest extends TestCase
{
    public function testPictureSourcesAreRetainedWithoutAnimationOptions(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['types' => ['content' => [
            'pictures' => ['_default' => [
                'sources' => [
                    ['srcset' => [['version' => 'avif']]],
                    ['srcset' => [['version' => 'webp']]],
                ],
                'img' => ['src_version' => 'avif'],
            ]],
        ]]]]);
        $picture = $config['types']['content']['pictures']['_default'];
        self::assertSame('avif', $picture['sources'][0]['srcset'][0]['version']);
        self::assertSame('webp', $picture['sources'][1]['srcset'][0]['version']);
        self::assertSame('avif', $picture['img']['src_version']);
    }

    public function testWebxIsNotAnAcceptedOutputFormat(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('webx');
        (new Processor())->processConfiguration(new Configuration(), [['types' => ['content' => [
            'versions' => ['invalid' => ['type' => 'webx']],
        ]]]]);
    }

    public function testResolvesDefaultsAfterMergingConfigFilesAndKeepsOnlyRelevantOptions(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [
            ['defaults' => ['avif_quality' => 85, 'webp_quality' => 82, 'animation' => ['max_frames' => 500, 'speed' => 6]]],
            ['types' => [
                'content' => [
                    'defaults' => [
                        'avif_quality' => 88,
                        'animation' => ['speed' => 5],
                    ],
                    'versions' => [
                        'avif' => ['type' => 'avif'],
                        'custom' => ['type' => 'avif', 'avif_quality' => 90, 'animation' => ['max_frames' => 100]],
                        'webp' => ['type' => 'webp'],
                        'still' => ['type' => 'avif', 'animated' => 'no'],
                        'jpeg' => ['type' => 'jpeg'],
                        'manual' => ['upload_requirements' => ['mimeTypes' => ['image/png'], 'animation' => 'no']],
                    ],
                ],
                'other' => ['versions' => ['avif' => ['type' => 'avif']]],
                'video' => ['type' => 'video', 'versions' => ['mp4' => ['upload_requirements' => ['mimeTypes' => ['video/mp4']]]]],
            ]],
        ]);
        self::assertSame(85, $config['types']['other']['versions']['avif']['avif_quality']);
        self::assertSame(['max_frames' => 500, 'speed' => 6], $config['types']['other']['versions']['avif']['animation']);
        self::assertSame(88, $config['types']['content']['defaults']['avif_quality']);
        $versions = $config['types']['content']['versions'];
        self::assertSame(88, $versions['avif']['avif_quality']);
        self::assertSame(['max_frames' => 500, 'speed' => 5], $versions['avif']['animation']);
        self::assertSame(90, $versions['custom']['avif_quality']);
        self::assertSame(['max_frames' => 100, 'speed' => 5], $versions['custom']['animation']);
        self::assertSame(82, $versions['webp']['webp_quality']);
        self::assertArrayNotHasKey('avif_quality', $versions['webp']);
        self::assertArrayNotHasKey('webp_quality', $versions['avif']);
        self::assertSame('keep', $versions['avif']['animated']);
        self::assertSame('accepted', $config['types']['content']['upload_requirements']['animation']);
        self::assertArrayNotHasKey('animation', $versions['still']);
        self::assertArrayNotHasKey('animated', $versions['jpeg']);
        self::assertArrayNotHasKey('animation', $versions['jpeg']);
        self::assertArrayNotHasKey('avif_quality', $versions['manual']);
        self::assertSame('no', $versions['manual']['upload_requirements']['animation']);
        self::assertArrayNotHasKey('avif_quality', $config['types']['video']['versions']['mp4']);
    }
}
