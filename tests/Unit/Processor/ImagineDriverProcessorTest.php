<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Processor;

use Imagick;
use Imagine\Gd\Imagine as GdImagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Metadata\MetadataBag;
use Imagine\Image\Palette\RGB;
use Imagine\Imagick\Image as ImagickImage;
use Imagine\Imagick\Imagine as ImagickImagine;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Processor\ImagineProcessor;
use Symfony\Component\HttpFoundation\File\File;
use WeakReference;

final class ImagineDriverProcessorTest extends TestCase
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

    private function version(array $options): MediaVersion
    {
        $file = tempnam(sys_get_temp_dir(), 'imagine-driver-test-');
        $this->files[] = $file;
        $file .= '.jpg';
        $this->files[] = $file;
        copy(__DIR__.'/resources/energy.250x141.96ppi.jpg', $file);
        $original = new MediaVersion('_original');
        $original->setFileMimeType('image/jpeg');
        $original->setWidth(250);
        $original->setHeight(141);
        $version = new MediaVersion('small');
        $version->setOriginalVersion($original);
        $version->setUpload(new File($file));
        $version->setOptions($options);

        return $version;
    }

    public function testDefaultAndExplicitDriversActuallyEncodeFiles(): void
    {
        foreach ([null, 'imagick', 'gd'] as $driver) {
            foreach (['png', 'webp', 'avif'] as $format) {
                $options = ['type' => $format, 'scale_width' => 100, 'avif_quality' => 90, 'webp_quality' => 82];
                if (null !== $driver) {
                    $options['imagine_driver'] = $driver;
                }
                $version = $this->version($options);
                $input = $version->getUpload()->getRealPath();
                (new ImagineProcessor())->process($version);
                self::assertFileDoesNotExist($input);
                $file = $version->getUpload()->getRealPath();
                $this->files[] = $file;
                $size = getimagesize($file);
                self::assertSame(100, $size[0]);
                self::assertSame(56, $size[1]);
                self::assertSame('image/'.$format, $size['mime']);
                self::assertGreaterThan(0, filesize($file));
            }
        }
    }

    public function testKeepTmpFilePreservesInputAndOutputWhenRequested(): void
    {
        $version = $this->version(['type' => 'png']);
        $input = $version->getUpload();
        $version->setUpload($input, true);
        (new ImagineProcessor())->process($version);
        $this->files[] = $version->getUpload()->getRealPath();
        self::assertFileExists($input->getRealPath());
        self::assertFileExists($version->getUpload()->getRealPath());
        self::assertTrue($version->isKeepTmpFile());
    }

    public function testImageLayerCyclesAreReleasedAfterConversion(): void
    {
        $imageReference = null;
        $imagine = $this->createMock(ImagineInterface::class);
        $imagine->method('open')->willReturnCallback(static function (string $path) use (&$imageReference): ImageInterface {
            $image = (new ImagickImagine())->open($path);
            $imageReference = WeakReference::create($image);

            return $image;
        });
        $processor = new class($imagine) extends ImagineProcessor {
            public function __construct(private ImagineInterface $imagine)
            {
            }

            protected function createImagine(string $driver): ImagineInterface
            {
                return $this->imagine;
            }
        };
        $automaticGc = gc_enabled();
        gc_disable();
        try {
            $version = $this->version(['type' => 'png']);
            $processor->process($version);
            $this->files[] = $version->getUpload()->getRealPath();
            self::assertInstanceOf(WeakReference::class, $imageReference);
            self::assertNull($imageReference->get(), 'Image/layer cycles must not retain native pixel buffers between conversions.');
        } finally {
            if ($automaticGc) {
                gc_enable();
            }
            gc_collect_cycles();
        }
    }

    public function testFailedConversionRemovesPartialOutputAndRetainsInput(): void
    {
        $version = $this->version(['type' => 'png']);
        $input = $version->getUpload();
        $output = substr($input->getRealPath(), 0, -4).'.png';
        $this->files[] = $output;
        $image = $this->createMock(ImageInterface::class);
        $image->method('getSize')->willReturn(new Box(250, 141));
        $image->method('save')->willReturnCallback(static function (string $path): never {
            file_put_contents($path, 'incomplete');
            throw new RuntimeException('Encoding failed');
        });
        $imagine = $this->createMock(ImagineInterface::class);
        $imagine->method('open')->willReturn($image);
        $processor = new class($imagine) extends ImagineProcessor {
            public function __construct(private ImagineInterface $imagine)
            {
            }

            protected function createImagine(string $driver): ImagineInterface
            {
                return $this->imagine;
            }
        };
        try {
            $processor->process($version);
            self::fail('The encoding failure must propagate.');
        } catch (RuntimeException $e) {
            self::assertSame('Encoding failed', $e->getMessage());
        }
        self::assertSame($input, $version->getUpload());
        self::assertFileExists($input->getRealPath());
        self::assertFileDoesNotExist($output);
    }

    public function testAvifConvertsNonRgbIccProfilesInsteadOfCopyingThem(): void
    {
        foreach (['GRAY', 'CMYK', 'RGB ', ''] as $space) {
            $native = $this->createMock(Imagick::class);
            $native->method('getImageProfiles')->with('icc')->willReturn('' === $space ? [] : ['icc' => str_repeat("\0", 16).$space]);
            $native->expects(in_array($space, ['GRAY', 'CMYK'], true) ? self::once() : self::never())->method('profileImage')->with('icc', (new RGB())->profile()->data());
            $image = new ImagickImage($native, new RGB(), new MetadataBag());
            $processor = new class extends ImagineProcessor {
                public function normalize(ImageInterface $image, string $format): void
                {
                    $this->normalizeAvifColorProfile($image, $format);
                }
            };
            $processor->normalize($image, 'avif');
        }
    }

    public function testNonAvifOutputDoesNotChangeColorProfiles(): void
    {
        $native = $this->createMock(Imagick::class);
        $native->expects(self::never())->method('getImageProfiles');
        $native->expects(self::never())->method('profileImage');
        $image = new ImagickImage($native, new RGB(), new MetadataBag());
        $processor = new class extends ImagineProcessor {
            public function normalize(ImageInterface $image, string $format): void
            {
                $this->normalizeAvifColorProfile($image, $format);
            }
        };
        $processor->normalize($image, 'webp');
    }

    public function testConfiguredDriverAndResamplingFilterArePassedToImagine(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->expects(self::once())->method('resize')->with(self::callback(static fn ($size): bool => 100 === $size->getWidth() && 56 === $size->getHeight()), ImageInterface::FILTER_MITCHELL)->willReturnSelf();
        $image->method('getSize')->willReturnOnConsecutiveCalls(new Box(250, 141), new Box(100, 56), new Box(100, 56));
        $image->expects(self::once())->method('save')->with(self::anything(), self::callback(static fn (array $options): bool => !isset($options['imagine_driver']) && 'jpeg' === $options['format']))
            ->willReturnCallback(static function (string $path) use ($image): ImageInterface {
                touch($path);

                return $image;
            });
        $imagine = $this->createMock(ImagineInterface::class);
        $imagine->expects(self::once())->method('open')->willReturn($image);
        $processor = new class($imagine) extends ImagineProcessor {
            public ?string $selectedDriver = null;

            public function __construct(private ImagineInterface $imagine)
            {
            }

            protected function createImagine(string $driver): ImagineInterface
            {
                $this->selectedDriver = $driver;

                return $this->imagine;
            }
        };
        $version = $this->version(['type' => 'jpeg', 'imagine_driver' => 'imagick', 'scale_width' => 100, 'resampling-filter' => ImageInterface::FILTER_MITCHELL]);
        $processor->process($version);
        $this->files[] = $version->getUpload()->getRealPath();
        self::assertSame('imagick', $processor->selectedDriver);
    }

    public function testFactoryUsesCorrectEngineAndRejectsUnknownDrivers(): void
    {
        $processor = new class extends ImagineProcessor {
            public function engine(string $driver): ImagineInterface
            {
                return $this->createImagine($driver);
            }
        };
        self::assertInstanceOf(ImagickImagine::class, $processor->engine('imagick'));
        self::assertInstanceOf(GdImagine::class, $processor->engine('gd'));
        $this->expectException(InvalidArgumentException::class);
        $processor->engine('invalid');
    }
}
