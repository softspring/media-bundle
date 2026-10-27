<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Processor\FfmpegProcessor;
use Softspring\MediaBundle\Processor\ImagineProcessor;
use Softspring\MediaBundle\Processor\ProcessorProvider;
use Softspring\MediaBundle\Processor\WebpAnimationNormalizer;
use Softspring\MediaBundle\Processor\VersionFileCopyProcessor;
use Softspring\MediaBundle\Storage\StorageDriverInterface;
use Softspring\MediaBundle\Tests\Fixtures\AnimationFixtures;
use Softspring\MediaBundle\Tools\AnimationDetector;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class AutomaticAnimationTest extends TestCase
{
    private array $files = [];

    protected function setUp(): void
    {
        if (null === (new ExecutableFinder())->find('ffmpeg') || null === (new ExecutableFinder())->find('ffprobe')) {
            self::markTestSkipped('FFmpeg and FFprobe are required for real animation tests.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            @unlink($path);
        }
    }

    public function testPngAnimationIsPreservedInAvifWithoutAnAnimationFlagAndRetainsAlpha(): void
    {
        $version = $this->encode(AnimationFixtures::png(2), ['type' => 'avif', 'scale_width' => 4, 'avif_quality' => 85]);
        self::assertSame(4, $version->getWidth());
        self::assertTrue((new AnimationDetector())->isAnimated($version->getUpload()->getRealPath()));
        $streams = $this->probe($version)['streams'];
        self::assertGreaterThanOrEqual(2, (int) $streams[0]['nb_read_frames']);
        self::assertCount(2, $streams, 'The alpha stream must be retained.');
    }

    public function testApngWithASeparateDefaultImagePreservesAllAnimationFrames(): void
    {
        $source = AnimationFixtures::png(4, true);
        foreach (['avif', 'png'] as $format) {
            $version = $this->encode($source, ['type' => $format]);
            self::assertTrue((new AnimationDetector())->isAnimated($version->getUpload()->getRealPath()));
            self::assertSame(4, (int) $this->probe($version)['streams'][0]['nb_read_frames']);
        }
    }

    public function testDownloadedOriginalIsDetectedAfterTheCopyStage(): void
    {
        $original = new MediaVersion('_original');
        $original->setFileMimeType('image/png');
        $original->setUrl('original.png');
        $version = new MediaVersion('generated');
        $version->setOriginalVersion($original);
        $version->setOptions(['type' => 'avif']);
        $storage = $this->createMock(StorageDriverInterface::class);
        $storage->expects(self::once())->method('download')->willReturnCallback(function ($url, $path): void {
            $this->files[] = $path;
            file_put_contents($path, AnimationFixtures::png(4, true));
        });
        $provider = new ProcessorProvider([
            new VersionFileCopyProcessor($storage),
            new FfmpegProcessor('ffmpeg', 'ffprobe', 60, new WebpAnimationNormalizer()),
            new ImagineProcessor(),
        ]);
        $provider->applyProcessors($version);
        $this->files[] = $version->getUpload()->getRealPath();

        self::assertSame(4, (int) $this->probe($version)['streams'][0]['nb_read_frames']);
    }

    public function testAnAnimatedAvifCanBeConvertedBackToApngWithoutLosingItsFrames(): void
    {
        $avif = $this->encode(AnimationFixtures::png(2), ['type' => 'avif']);
        $version = $this->encode(file_get_contents($avif->getUpload()->getRealPath()), ['type' => 'png'], 'image/avif');
        self::assertTrue((new AnimationDetector())->isAnimated($version->getUpload()->getRealPath()));
        self::assertSame(2, (int) $this->probe($version)['streams'][0]['nb_read_frames']);
    }

    public function testAnExplicitStillVersionDropsAnimation(): void
    {
        $version = $this->encode(AnimationFixtures::png(2), ['type' => 'avif', 'animated' => 'no']);
        self::assertFalse((new AnimationDetector())->isAnimated($version->getUpload()->getRealPath()));
        self::assertSame(1, (int) $this->probe($version)['streams'][0]['nb_read_frames']);
    }

    public function testJpegAutomaticallyDropsAnimation(): void
    {
        $version = $this->encode(AnimationFixtures::png(2), ['type' => 'jpeg', 'jpeg_quality' => 85]);
        self::assertSame('image/jpeg', $version->getUpload()->getMimeType());
        self::assertFalse((new AnimationDetector())->isAnimated($version->getUpload()->getRealPath()));
    }

    public function testStaticSourcesStayOnTheImaginePath(): void
    {
        $version = $this->encode(AnimationFixtures::png(), ['type' => 'png', 'imagine_driver' => 'gd', 'scale_width' => 4]);
        self::assertSame(4, $version->getWidth());
        self::assertFalse((new AnimationDetector())->isAnimated($version->getUpload()->getRealPath()));
    }

    public function testAnimationCannotUpscaleUnlessExplicitlyEnabled(): void
    {
        $version = $this->encode(AnimationFixtures::png(2), ['type' => 'png', 'scale_width' => 16]);
        self::assertSame(8, $version->getWidth());
        $upscaled = $this->encode(AnimationFixtures::png(2), ['type' => 'png', 'scale_width' => 16, 'allow_upscale' => true]);
        self::assertSame(16, $upscaled->getWidth());
    }

    public function testFrameLimitRejectsInsteadOfSilentlyTruncating(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('configured maximum is 2');
        $this->encode(AnimationFixtures::png(3), ['type' => 'avif', 'animation' => ['max_frames' => 2]]);
    }

    public function testAnimatedWebpUploadRetainsFramesAndVariableDelaysInAvif(): void
    {
        if (!extension_loaded('imagick')) {
            self::markTestSkipped('Imagick is required for animated WebP decoding.');
        }
        $webp = $this->encode(AnimationFixtures::png(3), ['type' => 'webp']);
        $avif = $this->encode(file_get_contents($webp->getUpload()->getRealPath()), ['type' => 'avif'], 'image/webp');
        $streams = $this->probe($avif)['streams'];
        self::assertSame(3, (int) $streams[0]['nb_read_frames']);
        self::assertCount(2, $streams, 'Transparency must survive WebP decoding.');
        self::assertEqualsWithDelta(0.6, (float) $streams[0]['duration'], 0.02);
    }

    private function encode(string $bytes, array $options, string $mime = 'image/png'): MediaVersion
    {
        $path = tempnam(sys_get_temp_dir(), 'auto-animation-');
        $this->files[] = $path;
        file_put_contents($path, $bytes);
        $original = new MediaVersion('_original');
        $original->setFileMimeType($mime);
        $version = new MediaVersion('generated');
        $version->setOriginalVersion($original);
        $version->setUpload(new File($path));
        $version->setOptions($options);
        $provider = new ProcessorProvider([
            new FfmpegProcessor('ffmpeg', 'ffprobe', 30, new WebpAnimationNormalizer()),
            new ImagineProcessor(),
        ]);
        try {
            $provider->applyProcessors($version);
        } finally {
            $this->files[] = $version->getUpload()->getRealPath();
        }

        return $version;
    }

    private function probe(MediaVersion $version): array
    {
        $probe = new Process(['ffprobe', '-v', 'error', '-count_frames', '-show_streams', '-of', 'json', $version->getUpload()->getRealPath()]);
        $probe->mustRun();

        return json_decode($probe->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
