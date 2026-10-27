<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\DependencyInjection\Configuration;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Helper\TypeChecker;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ImagineDriverConfigurationTest extends TestCase
{
    private function process(array $config, ?Configuration $configuration = null): array
    {
        $configuration ??= new class extends Configuration {
            protected function isImagineDriverAvailable(string $driver): bool
            {
                return true;
            }

            protected function getImagineDriverFormats(string $driver): array
            {
                return ['jpeg', 'png', 'apng', 'gif', 'webp', 'avif'];
            }

            protected function isExecutableAvailable(string $binary): bool
            {
                return true;
            }
        };

        return (new Processor())->processConfiguration($configuration, [$config]);
    }

    public function testDefaultAndGlobalTypeVersionPrecedenceAreResolvedIntoVersionOptions(): void
    {
        foreach (['imagick', 'gd'] as $global) {
            $config = $this->process(['imagine' => ['driver' => $global], 'types' => [
                'inherited' => ['versions' => ['small' => ['type' => 'webp']]],
                'overridden' => ['imagine_driver' => 'gd', 'versions' => ['small' => ['type' => 'webp'], 'large' => ['type' => 'avif', 'imagine_driver' => 'imagick']]],
            ]]);
            self::assertSame($global, $config['types']['inherited']['versions']['small']['imagine_driver']);
            self::assertSame('gd', $config['types']['overridden']['versions']['small']['imagine_driver']);
            self::assertSame('imagick', $config['types']['overridden']['versions']['large']['imagine_driver']);
        }
        $config = $this->process(['types' => ['image' => ['versions' => ['small' => []]]]]);
        self::assertSame('imagick', $config['imagine']['driver']);
        self::assertSame('imagick', $config['types']['image']['versions']['small']['imagine_driver']);
    }

    public function testMultipleConfigFilesUseTheFinalInheritedDriver(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [
            ['imagine' => ['driver' => 'imagick'], 'types' => ['image' => ['versions' => ['small' => ['type' => 'png']]]]],
            ['imagine' => ['driver' => 'gd']],
        ]);
        self::assertSame('gd', $config['types']['image']['versions']['small']['imagine_driver']);
    }

    public function testDriversApplyToAutomaticImagesButNotManualOrVideoVersions(): void
    {
        $config = $this->process(['types' => [
            'image' => ['imagine_driver' => 'gd', 'versions' => [
                'animated' => ['type' => 'avif', 'animated' => true],
                'poster' => ['upload_requirements' => ['mimeTypes' => ['image/png']]],
            ]],
            'video' => ['type' => 'video', 'versions' => ['poster' => ['upload_requirements' => ['mimeTypes' => ['image/png']]]]],
        ]]);
        self::assertSame('gd', $config['types']['image']['versions']['animated']['imagine_driver']);
        self::assertArrayNotHasKey('imagine_driver', $config['types']['image']['versions']['poster']);
        self::assertArrayNotHasKey('imagine_driver', $config['types']['video']['versions']['poster']);
    }

    public function testMissingSelectedDriverHasAnActionableConfigurationError(): void
    {
        $configuration = new class extends Configuration {
            protected function isImagineDriverAvailable(string $driver): bool
            {
                return 'gd' === $driver;
            }
        };
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('image.small" requires the "imagick"');
        $this->process(['types' => ['image' => ['versions' => ['small' => ['type' => 'png']]]]], $configuration);
    }

    public function testMissingCodecHasAnActionableConfigurationError(): void
    {
        $configuration = new class extends Configuration {
            protected function isImagineDriverAvailable(string $driver): bool
            {
                return true;
            }

            protected function getImagineDriverFormats(string $driver): array
            {
                return ['png', 'webp'];
            }
        };
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Imagine driver "imagick" does not support "avif"');
        $this->process(['types' => ['image' => ['versions' => ['small' => ['type' => 'avif']]]]], $configuration);
    }

    public function testSourceCodecIsValidatedEvenIfOutputIsSupported(): void
    {
        $configuration = new class extends Configuration {
            protected function isImagineDriverAvailable(string $driver): bool
            {
                return true;
            }

            protected function getImagineDriverFormats(string $driver): array
            {
                return ['png', 'webp'];
            }
        };
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('does not support "avif"');
        $this->process(['types' => ['image' => ['upload_requirements' => ['mimeTypes' => ['image/avif']], 'versions' => ['small' => ['type' => 'webp']]]]], $configuration);
    }

    public function testInvalidDriverNamesAreRejectedAtEveryLevel(): void
    {
        foreach ([['imagine' => ['driver' => 'invalid']], ['types' => ['image' => ['imagine_driver' => 'invalid']]], ['types' => ['image' => ['versions' => ['small' => ['imagine_driver' => 'invalid']]]]]] as $config) {
            try {
                $this->process($config);
                self::fail('Invalid driver must not be accepted');
            } catch (InvalidConfigurationException $exception) {
                self::assertStringContainsString('invalid', $exception->getMessage());
            }
        }
    }

    public function testGlobalDriverChangeOnlyRegeneratesVersionsThatInheritIt(): void
    {
        $raw = ['types' => ['image' => ['versions' => ['inherited' => ['type' => 'webp'], 'pinned' => ['type' => 'webp', 'imagine_driver' => 'gd']]]]];
        $before = $this->process($raw);
        $raw['imagine']['driver'] = 'gd';
        $after = $this->process($raw);
        $media = new Media();
        foreach ($before['types']['image']['versions'] as $name => $options) {
            $version = new MediaVersion($name, $media);
            $version->setOptions($options);
        }
        $result = TypeChecker::checkMedia($media, $after['types']['image']);
        self::assertSame(['inherited'], array_keys($result['changed']));
        self::assertSame(['pinned'], $result['ok']);
        self::assertSame('gd', $result['changed']['inherited']['imagine_driver']['config']);
    }

    public function testDriverOnUploadedVersionIsRejected(): void
    {
        foreach ([['upload_requirements' => ['mimeTypes' => ['image/png']]]] as $version) {
            try {
                $this->process(['types' => ['image' => ['versions' => ['small' => $version + ['imagine_driver' => 'gd']]]]]);
                self::fail('Non-Imagine versions must not accept an Imagine driver');
            } catch (InvalidConfigurationException $exception) {
                self::assertStringContainsString('generated image versions', $exception->getMessage());
            }
        }
    }

    public function testResizePolicyInheritanceAndPersistenceIntoOptions(): void
    {
        $raw = ['imagine' => ['allow_upscale' => true, 'resize_tolerance' => 0.05, 'preserve_original_encoding' => false], 'types' => [
            'global' => ['versions' => ['large' => ['type' => 'webp']]],
            'local' => ['allow_upscale' => false, 'resize_tolerance' => 0.01, 'preserve_original_encoding' => true, 'versions' => [
                'inherited' => ['type' => 'webp'],
                'override' => ['type' => 'webp', 'allow_upscale' => true, 'resize_tolerance' => 0, 'preserve_original_encoding' => false],
            ]],
        ]];
        $config = $this->process($raw);
        foreach (['allow_upscale' => true, 'resize_tolerance' => 0.05, 'preserve_original_encoding' => false] as $key => $value) {
            self::assertSame($value, $config['types']['global']['versions']['large'][$key]);
        }
        foreach (['allow_upscale' => false, 'resize_tolerance' => 0.01, 'preserve_original_encoding' => true] as $key => $value) {
            self::assertSame($value, $config['types']['local']['versions']['inherited'][$key]);
        }
        foreach (['allow_upscale' => true, 'resize_tolerance' => 0, 'preserve_original_encoding' => false] as $key => $value) {
            self::assertSame($value, $config['types']['local']['versions']['override'][$key]);
        }
        $media = new Media();
        $version = new MediaVersion('large', $media);
        $version->setOptions($config['types']['global']['versions']['large']);
        $raw['imagine']['allow_upscale'] = false;
        $after = $this->process($raw);
        $check = TypeChecker::checkMedia($media, $after['types']['global']);
        self::assertArrayHasKey('allow_upscale', $check['changed']['large']);
        self::assertArrayNotHasKey('resize_tolerance', $check['changed']['large']);
    }

    public function testInvalidResizeToleranceIsRejectedAtEveryLevel(): void
    {
        foreach ([-0.01, 1.01] as $tolerance) {
            foreach ([['imagine' => ['resize_tolerance' => $tolerance]], ['types' => ['image' => ['resize_tolerance' => $tolerance]]], ['types' => ['image' => ['versions' => ['large' => ['resize_tolerance' => $tolerance]]]]]] as $config) {
                try {
                    $this->process($config);
                    self::fail('Invalid tolerance must be rejected');
                } catch (InvalidConfigurationException $exception) {
                    self::assertStringContainsString('resize_tolerance', $exception->getMessage());
                }
            }
        }
    }
}
