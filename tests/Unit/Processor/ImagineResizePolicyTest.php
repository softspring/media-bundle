<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Processor;

use Imagine\Image\Box;
use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Processor\ImagineProcessor;
use Symfony\Component\HttpFoundation\File\File;

final class ImagineResizePolicyTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach (array_unique($this->files) as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function version(array $options): array
    {
        $source = tempnam(sys_get_temp_dir(), 'resize-policy-source-');
        $this->files[] = $source;
        $image = imagecreatetruecolor(1127, 295);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
        for ($x = 0; $x < 1000; $x += 10) {
            imagefilledrectangle($image, $x, 60, $x + 6, 220, imagecolorallocatealpha($image, $x % 255, 80, 190, 0));
        }
        imagewebp($image, $source, 82);
        $copy = tempnam(sys_get_temp_dir(), 'resize-policy-copy-');
        $this->files[] = $copy;
        copy($source, $copy);
        $original = new MediaVersion('_original');
        $original->setFileMimeType('image/webp');
        $original->setWidth(1127);
        $original->setHeight(295);
        $original->setUpload(new File($source));
        $version = new MediaVersion('large');
        $version->setOriginalVersion($original);
        $version->setUpload(new File($copy));
        $version->setOptions($options + ['type' => 'webp']);

        return [$version, $source];
    }

    public function testDimensionsRespectUpscaleToleranceAndStrictShapes(): void
    {
        $processor = new class extends ImagineProcessor {
            public function dimensions(array $options): array
            {
                $size = $this->calculateDimensions(new Box(100, 50), $options);

                return [$size->getWidth(), $size->getHeight()];
            }
        };
        foreach ([
            [[], [100, 50]],
            [['scale_width' => 200], [100, 50]],
            [['scale_width' => 200, 'allow_upscale' => true], [200, 100]],
            [['scale_height' => 100], [100, 50]],
            [['scale_width' => 98], [100, 50]],
            [['scale_height' => 49], [100, 50]],
            [['scale_width' => 98, 'resize_tolerance' => 0], [98, 49]],
            [['scale_width' => 98, 'resize_tolerance' => 0.01], [98, 49]],
            [['scale_width' => 97], [97, 48]],
            [['scale_width' => 98, 'scale_height' => 49], [98, 49]],
            [['scale_width' => 200, 'scale_height' => 200], [50, 50]],
            [['scale_width' => 1], [1, 1]],
        ] as [$options, $expected]) {
            self::assertSame($expected, $processor->dimensions($options), json_encode($options));
        }
    }

    public function testNearSizeSameFormatPreservesBytesAndIndependentFile(): void
    {
        foreach (['gd', 'imagick'] as $driver) {
            [$version, $source] = $this->version(['scale_width' => 1120, 'imagine_driver' => $driver, 'webp_quality' => 5]);
            (new ImagineProcessor())->process($version);
            self::assertSame(1127, $version->getWidth());
            self::assertSame(295, $version->getHeight());
            self::assertSame(hash_file('sha256', $source), hash_file('sha256', $version->getUpload()->getRealPath()));
            self::assertNotSame($source, $version->getUpload()->getRealPath());
        }
    }

    public function testZeroToleranceForcesExactResize(): void
    {
        [$version, $source] = $this->version(['scale_width' => 1120, 'resize_tolerance' => 0]);
        (new ImagineProcessor())->process($version);
        $this->files[] = $version->getUpload()->getRealPath();
        self::assertSame(1120, $version->getWidth());
        self::assertSame(293, $version->getHeight());
        self::assertNotSame(hash_file('sha256', $source), hash_file('sha256', $version->getUpload()->getRealPath()));
    }

    public function testPreservationCanBeDisabledWithoutChangingDimensions(): void
    {
        [$version, $source] = $this->version(['scale_width' => 1120, 'preserve_original_encoding' => false, 'webp_quality' => 5]);
        (new ImagineProcessor())->process($version);
        $this->files[] = $version->getUpload()->getRealPath();
        self::assertSame(1127, $version->getWidth());
        self::assertNotSame(hash_file('sha256', $source), hash_file('sha256', $version->getUpload()->getRealPath()));
    }

    public function testDifferentFormatStillEncodesAtActualRetainedDimensions(): void
    {
        [$version] = $this->version(['type' => 'avif', 'scale_width' => 1120]);
        (new ImagineProcessor())->process($version);
        $this->files[] = $version->getUpload()->getRealPath();
        $size = getimagesize($version->getUpload()->getRealPath());
        self::assertSame('image/avif', $size['mime']);
        self::assertSame([1127, 295], [$size[0], $size[1]]);
    }

    public function testUpscalingCanBeEnabledExplicitly(): void
    {
        foreach ([false, true] as $allowed) {
            [$version] = $this->version(['scale_width' => 2000, 'allow_upscale' => $allowed]);
            (new ImagineProcessor())->process($version);
            $this->files[] = $version->getUpload()->getRealPath();
            self::assertSame($allowed ? 2000 : 1127, $version->getWidth());
        }
    }

    public function testExplicitResolutionOrFlattenIsNotSkipped(): void
    {
        foreach ([['resolution-x' => 72, 'resolution-y' => 72], ['flatten' => true]] as $options) {
            [$version, $source] = $this->version($options);
            (new ImagineProcessor())->process($version);
            $this->files[] = $version->getUpload()->getRealPath();
            self::assertNotSame(hash_file('sha256', $source), hash_file('sha256', $version->getUpload()->getRealPath()));
        }
    }
}
