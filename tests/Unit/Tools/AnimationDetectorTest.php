<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Tests\Fixtures\AnimationFixtures;
use Softspring\MediaBundle\Tools\AnimationDetector;

final class AnimationDetectorTest extends TestCase
{
    public function testDetectsActualPngFramesRegardlessOfExtension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'animation-');
        try {
            file_put_contents($path, AnimationFixtures::png());
            self::assertFalse((new AnimationDetector())->isAnimated($path));
            file_put_contents($path, AnimationFixtures::png(2));
            self::assertTrue((new AnimationDetector())->isAnimated($path));
        } finally {
            unlink($path);
        }
    }

    public function testSingleFrameAndTruncatedApngAreNotAnimated(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'animation-');
        try {
            $header = "\x89PNG\r\n\x1A\n".pack('N', 8).'acTL';
            foreach ([pack('NN', 1, 0), "\x00\x00"] as $data) {
                file_put_contents($path, $header.$data);
                self::assertFalse((new AnimationDetector())->isAnimated($path));
            }
        } finally {
            unlink($path);
        }
    }

    public function testPersistedOriginalAnimationFlagAndLegacyApngMimeType(): void
    {
        $media = new Media();
        $original = new MediaVersion('_original', $media);
        $original->setFileMimeType('image/apng');
        $detector = new AnimationDetector();
        self::assertTrue($detector->isAnimatedMedia($media));
        $media->setMetadataField('animated', false);
        self::assertFalse($detector->isAnimatedMedia($media));
        $original->setFileMimeType('image/avif');
        $media->setMetadataField('animated', true);
        self::assertTrue($detector->isAnimatedMedia($media));
    }

    public function testWebpRequiresMultipleFrameChunks(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'animation-');
        try {
            foreach ([1, 2] as $count) {
                $body = 'WEBP'.str_repeat('ANMF'.pack('V', 0), $count);
                file_put_contents($path, 'RIFF'.pack('V', strlen($body)).$body);
                self::assertSame($count > 1, (new AnimationDetector())->isAnimated($path));
            }
        } finally {
            unlink($path);
        }
    }
}
