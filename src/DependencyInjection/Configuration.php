<?php

namespace Softspring\MediaBundle\DependencyInjection;

use Imagick;
use Imagine\Image\ImageInterface;
use Softspring\MediaBundle\Media\DefaultNameGenerator;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Process\ExecutableFinder;

class Configuration implements ConfigurationInterface
{
    private const string HELP_AVIF = <<<HELP
Avif format is not supported by your PHP environment. Please add support for it.

Depending on your system, add the following packages:

    $ apt install libavif-dev # Debian/Ubuntu

You will need to compile GD with avif support: --with-avif

In docker, you can add the following lines to your Dockerfile:

    RUN apk --no-cache add libavif-dev && \
        docker-php-ext-configure gd --with-avif && \
        docker-php-ext-install gd
HELP;

    private const string HELP_FFMPEG = <<<'HELP'
Animated media versions require executable FFmpeg and FFprobe binaries. The following configured binaries were not found or are not executable: %s.

Install FFmpeg in the application runtime, for example:

    $ apt install ffmpeg # Debian/Ubuntu
    $ apk add --no-cache ffmpeg # Alpine

In Docker, add the ffmpeg package to the runtime image.
HELP;

    protected function getSupportedMimeTypes(): array
    {
        $supportedTypes = [
            'mime' => [
                'image/svg+xml',
                'image/svg',
                'video/webm',
                'video/mp4',
            ],
            'versionTypeExtensions' => [
                'keep',
                'svg',
            ],
        ];

        if (function_exists('imagegif') && function_exists('imagecreatefromgif')) {
            $supportedTypes['versionTypeExtensions'][] = 'gif';
            $supportedTypes['mime'][] = 'image/gif';
        }
        if (function_exists('imagejpeg') && function_exists('imagecreatefromjpeg')) {
            $supportedTypes['versionTypeExtensions'][] = 'jpeg';
            $supportedTypes['mime'][] = 'image/jpeg';
        }
        if (function_exists('imagewebp') && function_exists('imagecreatefromwebp')) {
            $supportedTypes['versionTypeExtensions'][] = 'webp';
            $supportedTypes['mime'][] = 'image/webp';
        }
        if (function_exists('imageavif') && function_exists('imagecreatefromavif')) {
            $supportedTypes['versionTypeExtensions'][] = 'avif';
            $supportedTypes['mime'][] = 'image/avif';
        }
        if (function_exists('imagepng') && function_exists('imagecreatefrompng')) {
            $supportedTypes['versionTypeExtensions'][] = 'png';
            $supportedTypes['versionTypeExtensions'][] = 'apng';
            $supportedTypes['mime'][] = 'image/png';
            $supportedTypes['mime'][] = 'image/apng';
        }

        if ($this->isImagineDriverAvailable('imagick')) {
            foreach ($this->getImagineDriverFormats('imagick') as $format) {
                $supportedTypes['versionTypeExtensions'][] = $format;
                $supportedTypes['mime'][] = 'image/'.$format;
            }
        }
        $supportedTypes['mime'] = array_values(array_unique($supportedTypes['mime']));
        $supportedTypes['versionTypeExtensions'] = array_values(array_unique($supportedTypes['versionTypeExtensions']));

        return $supportedTypes;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sfs_media');

        $rootNode = $treeBuilder->getRootNode();

        $supportedMimeTypes = $this->getSupportedMimeTypes();

        $rootNode
            ->validate()
                ->always(function (array $config): array {
                    $config['types'] = self::fixConfigTypes($config['types'] ?? [], $config['imagine']['driver'], $config['imagine'], $config['defaults']);
                    $this->validateImagineDrivers($config);

                    return $config;
                })
            ->end()
            ->validate()
                ->ifTrue(function (array $config): bool {
                    return 'google_cloud_storage' === $config['driver'] && empty($config['google_cloud_storage']);
                })
                ->thenInvalid('google_cloud_storage config block is required when driver is google_cloud_storage.')
            ->end()
            ->validate()
                ->ifTrue(function (array $config): bool {
                    return 'filesystem' === $config['driver'] && empty($config['filesystem']);
                })
                ->thenInvalid('filesystem config block is required when driver is filesystem.')
            ->end()
            ->validate()
                ->ifTrue(fn (array $config): bool => [] !== $this->getMissingAnimationBinaries($config))
                ->then(function (array $config): array {
                    throw new InvalidConfigurationException(sprintf(self::HELP_FFMPEG, implode(', ', $this->getMissingAnimationBinaries($config))));
                })
            ->end()
            ->beforeNormalization()
                ->always(function (array $config): array {
                    if (empty($config['driver'])) {
                        $config['driver'] = empty($config['google_cloud_storage']) ? 'filesystem' : 'google_cloud_storage';
                    }

                    if ('filesystem' === $config['driver'] && empty($config['filesystem'])) {
                        $config['filesystem']['path'] = '%kernel.project_dir%/public/media';
                        $config['filesystem']['url'] = '/media';
                    }

                    return $config;
                })
            ->end()

        ;

        $rootNode->children()
                ->scalarNode('entity_manager')
                    ->defaultValue('default')
                ->end()

                ->enumNode('driver')
                    ->defaultValue('filesystem')
                    ->values(['filesystem', 'google_cloud_storage'])
                ->end()

                ->arrayNode('google_cloud_storage')
                    ->children()
                        ->scalarNode('bucket')->end()
                        ->scalarNode('public_base_url')->defaultNull()->end()
                        ->integerNode('delayed_deletion_days')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (?int $days): bool => null !== $days && $days <= 0)
                                ->thenInvalid('delayed_deletion_days must be a positive integer or null.')
                            ->end()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('filesystem')
                    ->children()
                        ->scalarNode('path')->defaultValue('%kernel.project_dir%/public/media')->end()
                        ->scalarNode('url')->defaultValue('/media')->end()
                    ->end()
                ->end()

                ->append($this->getDefaultsNode())

                ->arrayNode('imagine')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('driver')->values(['gd', 'imagick'])->defaultValue('imagick')->end()
                        ->booleanNode('allow_upscale')->defaultFalse()->end()
                        ->floatNode('resize_tolerance')->min(0)->max(1)->defaultValue(0.02)->end()
                        ->booleanNode('preserve_original_encoding')->defaultTrue()->end()
                    ->end()
                ->end()

                ->arrayNode('ffmpeg')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('binary')->defaultValue('ffmpeg')->cannotBeEmpty()->end()
                        ->scalarNode('probe_binary')->defaultValue('ffprobe')->cannotBeEmpty()->end()
                        ->integerNode('timeout')->defaultValue(300)->min(1)->end()
                    ->end()
                ->end()

                ->arrayNode('media')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')->defaultValue('Softspring\MediaBundle\Entity\Media')->end()
                        ->scalarNode('find_field_name')->defaultValue('id')->end()
                        ->booleanNode('admin_controller')->defaultTrue()->end()
                    ->end()
                ->end()

                ->arrayNode('version')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')->defaultValue('Softspring\MediaBundle\Entity\MediaVersion')->end()
                        ->scalarNode('find_field_name')->defaultValue('id')->end()
                    ->end()
                ->end()

                ->arrayNode('types')
                    ->useAttributeAsKey('key')
                    ->prototype('array')
                        ->validate()
                            ->ifTrue(function (array $config) use ($supportedMimeTypes): bool {
                                if (in_array('image/avif', $config['upload_requirements']['mimeTypes'] ?? [])) {
                                    return !in_array('image/avif', $supportedMimeTypes['mime']);
                                }

                                return false; // is valid
                            })
                            ->thenInvalid(Configuration::HELP_AVIF." \n\n%s")
                        ->end()
                        ->validate()
                            ->ifTrue(function (array $config) use ($supportedMimeTypes): bool {
                                foreach ($config['upload_requirements']['mimeTypes'] ?? [] as $mimeType) {
                                    if (!in_array($mimeType, $supportedMimeTypes['mime'])) {
                                        return true;
                                    }
                                }

                                return false;
                            })
                            ->thenInvalid('Some configured upload_requirements mimeTypes are not supported. The allowed formats are: '.implode(', ', $this->getSupportedMimeTypes()['mime']).'. Maybe you need to install some libraries to support them.'." \n\n%s")
                        ->end()
                        ->validate()
                            ->ifTrue(function (array $config) use ($supportedMimeTypes): bool {
                                foreach ($config['versions'] as $version) {
                                    if ((($version['type'] ?? '') === 'avif') && !in_array('image/avif', $supportedMimeTypes['mime'])) {
                                        return true;
                                    }
                                }

                                return false; // is valid
                            })
                            ->thenInvalid(Configuration::HELP_AVIF." \n\n%s")
                        ->end()
                        ->validate()
                            ->ifTrue(function (array $config) use ($supportedMimeTypes): bool {
                                foreach ($config['versions'] as $version) {
                                    if (!empty($version['type']) && !in_array($version['type'], $supportedMimeTypes['versionTypeExtensions'])) {
                                        return true;
                                    }
                                }

                                return false;
                            })
                            ->thenInvalid('Some configured version types are not supported. The allowed formats are: '.implode(', ', $this->getSupportedMimeTypes()['versionTypeExtensions']).'. Maybe you need to install some libraries to support them.'." \n\n%s")
                        ->end()
                        ->validate()
                            ->ifTrue(function (array $config) use ($supportedMimeTypes): bool {
                                foreach ($config['versions'] as $version) {
                                    foreach ($version['upload_requirements']['mimeTypes'] ?? [] as $mimeType) {
                                        if (!in_array($mimeType, $supportedMimeTypes['mime'])) {
                                            return true;
                                        }
                                    }
                                }

                                return false;
                            })
                            ->thenInvalid('Some configured version upload_requirements mimeTypes are not supported. The allowed formats are: '.implode(', ', $this->getSupportedMimeTypes()['mime']).'. Maybe you need to install some libraries to support them.'." \n\n%s")
                        ->end()
                        ->validate()
                            ->ifTrue(function (array $config): bool { /* use ($supportedMimeTypes) */
                                $type = $config['type'];

                                foreach ($config['upload_requirements']['mimeTypes'] ?? [] as $mimeType) {
                                    switch ($type) {
                                        case 'image':
                                            if (!str_starts_with($mimeType, 'image/')) {
                                                return true;
                                            }
                                            break;

                                        case 'video':
                                            if (!str_starts_with($mimeType, 'video/')) {
                                                return true;
                                            }
                                            break;
                                    }
                                }

                                return false;
                            })
                            ->thenInvalid('Some of the allowed mimeTypes are not compatible with media type (image, video). Check your configuration.'." \n\n%s")
                        ->end()

                        ->children()
                            ->enumNode('imagine_driver')->values(['gd', 'imagick'])->end()
                            ->booleanNode('allow_upscale')->end()
                            ->floatNode('resize_tolerance')->min(0)->max(1)->end()
                            ->booleanNode('preserve_original_encoding')->end()
                            ->enumNode('type')
                                ->values(['video', 'image'])
                                ->defaultValue('image')
                            ->end()
                            ->scalarNode('name')->end()
                            ->booleanNode('private')->defaultFalse()->end()
                            ->scalarNode('description')->end()
                            ->append($this->getDefaultsNode())

                            ->scalarNode('generator')->defaultValue(DefaultNameGenerator::class)->end()
                            ->append($this->getUploadRequirementsNode())
                            ->append($this->getVersionsNode())
                            ->append($this->getPicturesNode())
                            ->append($this->getVideoSetsNode())
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * @return list<string>
     */
    protected function getMissingAnimationBinaries(array $config): array
    {
        if (!$this->hasAnimatedVersions($config)) {
            return [];
        }

        $binaries = [
            'ffmpeg' => $config['ffmpeg']['binary'] ?? 'ffmpeg',
            'ffprobe' => $config['ffmpeg']['probe_binary'] ?? 'ffprobe',
        ];

        $missingBinaries = array_filter(
            $binaries,
            fn (string $binary): bool => !$this->isExecutableAvailable($binary),
        );

        return array_map(
            fn (string $name): string => sprintf('%s (%s)', $name, $binaries[$name]),
            array_keys($missingBinaries),
        );
    }

    protected function isExecutableAvailable(string $binary): bool
    {
        return null !== (new ExecutableFinder())->find($binary);
    }

    protected function isImagineDriverAvailable(string $driver): bool
    {
        return extension_loaded($driver) && ('imagick' !== $driver || class_exists(Imagick::class));
    }

    /** @return list<string> */
    protected function getImagineDriverFormats(string $driver): array
    {
        if (!$this->isImagineDriverAvailable($driver)) {
            return [];
        }
        $formats = [];
        foreach (['jpeg', 'png', 'gif', 'webp', 'avif'] as $format) {
            $supported = 'imagick' === $driver
                ? [] !== Imagick::queryFormats(strtoupper($format))
                : 2 === count(array_filter(['image'.$format, 'imagecreatefrom'.$format], 'function_exists'));
            if ($supported) {
                $formats[] = $format;
                if ('png' === $format) {
                    $formats[] = 'apng';
                }
            }
        }

        return $formats;
    }

    private function validateImagineDrivers(array $config): void
    {
        $formats = [];
        foreach ($config['types'] ?? [] as $typeName => $type) {
            foreach ($type['versions'] ?? [] as $versionName => $version) {
                if (!isset($version['imagine_driver'])) {
                    continue;
                }
                $driver = $version['imagine_driver'];
                if (!$this->isImagineDriverAvailable($driver)) {
                    throw new InvalidConfigurationException(sprintf('Media version "%s.%s" requires the "%s" Imagine driver. Install and enable ext-%s or configure another imagine_driver.', $typeName, $versionName, $driver, $driver));
                }
                $formats[$driver] ??= $this->getImagineDriverFormats($driver);
                $requiredFormats = 'keep' === $version['type'] ? [] : [$version['type']];
                $sourceConfig = $type['versions'][$version['from']] ?? [];
                $sourceMimeTypes = $sourceConfig['upload_requirements']['mimeTypes'] ?? $type['upload_requirements']['mimeTypes'] ?? [];
                foreach ($sourceMimeTypes as $mime) {
                    if (in_array($mime, ['image/jpeg', 'image/png', 'image/apng', 'image/gif', 'image/webp', 'image/avif'], true)) {
                        $requiredFormats[] = substr($mime, 6);
                    }
                }
                foreach (array_unique($requiredFormats) as $format) {
                    if (!in_array($format, $formats[$driver], true)) {
                        throw new InvalidConfigurationException(sprintf('Media version "%s.%s": Imagine driver "%s" does not support "%s". Install the corresponding image codec (ImageMagick AVIF requires libheif/HEIC support), or override imagine_driver for this type/version.', $typeName, $versionName, $driver, $format));
                    }
                }
            }
        }
    }

    private function hasAnimatedVersions(array $config): bool
    {
        // Accepting animation does not require an encoder for static uploads.
        // Required animation must be usable as soon as the container boots.
        foreach ($config['types'] ?? [] as $type) {
            if ('required' === ($type['upload_requirements']['animation'] ?? 'accepted')) {
                return true;
            }
        }

        return false;
    }

    public function getAnimationOptionsNode(): NodeDefinition
    {
        $node = (new TreeBuilder('animation'))->getRootNode();
        $node->children()
            ->integerNode('fps')->min(1)->max(120)->end()
            ->integerNode('loop')->min(0)->max(65535)->end()
            ->integerNode('crf')->min(0)->max(63)->end()
            ->integerNode('speed')->min(0)->max(8)->end()
            ->integerNode('keyframe_interval')->min(1)->end()
            ->floatNode('max_duration')->min(0.001)->end()
            ->integerNode('max_frames')->min(2)->end()
        ->end();

        return $node;
    }

    public function getDefaultsNode(): NodeDefinition
    {
        /** @var ArrayNodeDefinition $node */
        $node = (new TreeBuilder('defaults'))->getRootNode();
        $node->normalizeKeys(false)->addDefaultsIfNotSet();
        $children = $node->children();
        $children->enumNode('imagine_driver')->values(['gd', 'imagick'])->end();
        $children->booleanNode('allow_upscale')->end();
        $children->floatNode('resize_tolerance')->min(0)->max(1)->end();
        $children->booleanNode('preserve_original_encoding')->end();
        $children->integerNode('scale_width')->min(1)->end();
        $children->integerNode('scale_height')->min(1)->end();
        $children->integerNode('png_compression_level')->min(0)->max(9)->end();
        $children->integerNode('webp_quality')->min(0)->max(100)->end();
        $children->integerNode('jpeg_quality')->min(0)->max(100)->end();
        $children->integerNode('avif_quality')->min(0)->max(100)->end();
        $children->booleanNode('flatten')->end();
        $children->integerNode('resolution-x')->end();
        $children->integerNode('resolution-y')->end();
        $children->scalarNode('resampling-filter')->end();
        $children->scalarNode('resolution-units')->end();
        $children->enumNode('animated')->values(['keep', 'no'])->end();
        $children->append($this->getAnimationOptionsNode());

        return $node;
    }

    public function getUploadRequirementsNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('upload_requirements');

        $node = $treeBuilder->getRootNode();

        $node
            ->children()
                ->enumNode('animation')->values(['no', 'accepted', 'required'])->end()
                ->integerNode('minWidth')->end()
                ->integerNode('minHeight')->end()
                ->integerNode('maxWidth')->end()
                ->integerNode('maxHeight')->end()
                ->integerNode('maxRatio')->end()
                ->integerNode('minRatio')->end()
                ->integerNode('minPixels')->end()
                ->integerNode('maxPixels')->end()
                ->booleanNode('allowSquare')->end()
                ->booleanNode('allowLandscape')->end()
                ->booleanNode('allowPortrait')->end()
                ->booleanNode('detectCorrupted')->end()
                ->scalarNode('maxSize')->end()
                ->booleanNode('binaryFormat')->end()
                ->arrayNode('mimeTypes')->scalarPrototype()->end()->end()
            ->end()
        ;

        return $node;
    }

    public function getVersionsNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('versions');

        $node = $treeBuilder->getRootNode();

        $versionNode = $node->arrayPrototype();
        $versionNode
            ->normalizeKeys(false)
            ->validate()
                ->ifTrue(static fn (array $version): bool => isset($version['upload_requirements']) && (isset($version['animated']) || isset($version['animation'])))
                ->thenInvalid('Animation processing options belong to generated versions. Use upload_requirements.animation for manual uploads.')
            ->end();

        $versionNode->validate()
            ->ifTrue(static fn (array $version): bool => isset($version['upload_requirements']) && [] !== array_intersect(['imagine_driver', 'allow_upscale', 'resize_tolerance', 'preserve_original_encoding'], array_keys($version)))
            ->thenInvalid('Imagine processing options belong to generated image versions, not manual uploads.')
        ->end();

        $versionNode->children()
                    ->enumNode('imagine_driver')->values(['gd', 'imagick'])->end()
                    ->booleanNode('allow_upscale')->end()
                    ->floatNode('resize_tolerance')->min(0)->max(1)->end()
                    ->booleanNode('preserve_original_encoding')->end()
                    ->append($this->getUploadRequirementsNode())
                    ->enumNode('type')->values(['jpeg', 'png', 'webp', 'keep', 'apng', 'avif', 'gif'])->end()
                    ->enumNode('animated')
                        ->beforeNormalization()->ifTrue(static fn (mixed $value): bool => is_bool($value))->then(static fn (bool $value): string => $value ? 'keep' : 'no')->end()
                        ->values(['keep', 'no'])
                    ->end()
                    ->append($this->getAnimationOptionsNode())
                    ->integerNode('scale_width')->end()
                    ->integerNode('scale_height')->end()
                    ->integerNode('png_compression_level')->end()
                    ->integerNode('webp_quality')->end()
                    ->integerNode('jpeg_quality')->end()
                    ->integerNode('avif_quality')->end()
                    ->booleanNode('flatten')->end()
                    ->integerNode('resolution-x')->end()
                    ->integerNode('resolution-y')->end()
                    ->scalarNode('resampling-filter')->end()
                    ->scalarNode('resolution-units')->end()
                    ->scalarNode('from')->defaultValue('_original')->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    public function getPicturesNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('pictures');

        $node = $treeBuilder->getRootNode();

        $node
            ->useAttributeAsKey('key')
            ->arrayPrototype()
                ->children()
                    ->arrayNode('img')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('src_version')->defaultValue('_original')->end()
                            ->scalarNode('dimensions_version')->end()
                        ->end()
                    ->end()
                    ->arrayNode('sources')
                        ->arrayPrototype()
                            ->children()
                                ->booleanNode('skip_if_animated')->defaultFalse()->end()
                                ->arrayNode('srcset')
                                    ->arrayPrototype()
                                        ->children()
                                            ->scalarNode('version')->isRequired()->end()
                                            ->scalarNode('suffix')->defaultValue('')->end()
                                        ->end()
                                    ->end()
                                ->end()
                                ->arrayNode('attrs')
                                    ->scalarPrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    public function getVideoSetsNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('video_sets');

        $node = $treeBuilder->getRootNode();

        $node
            ->useAttributeAsKey('key')
            ->arrayPrototype()
                ->children()
                    ->arrayNode('sources')
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('version')->isRequired()->end()
                                ->arrayNode('attrs')
                                    ->scalarPrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->scalarNode('poster_version')->end()
                    ->arrayNode('attrs')
                        ->scalarPrototype()->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    /**
     * Update config types, this can not be done in processors because it would be not used to compare with database versions.
     * Also, can not be set in configuration as default values, because is exclusive for some types.
     */
    public static function fixConfigTypes(?array $types = null, string $defaultImagineDriver = 'imagick', array $defaultPolicy = [], array $defaults = []): ?array
    {
        if (null === $types) {
            return null;
        }

        $policy = array_replace(['allow_upscale' => false, 'resize_tolerance' => 0.02, 'preserve_original_encoding' => true], array_intersect_key($defaultPolicy, array_flip(['allow_upscale', 'resize_tolerance', 'preserve_original_encoding'])));
        $keys = array_flip(['imagine_driver', 'allow_upscale', 'resize_tolerance', 'preserve_original_encoding', 'scale_width', 'scale_height', 'png_compression_level', 'webp_quality', 'jpeg_quality', 'avif_quality', 'flatten', 'resolution-x', 'resolution-y', 'resampling-filter', 'resolution-units', 'animated', 'animation']);
        $qualities = ['avif_quality' => ['avif', 'keep'], 'webp_quality' => ['webp', 'keep'], 'jpeg_quality' => ['jpeg', 'keep'], 'png_compression_level' => ['png', 'apng', 'keep']];
        foreach ($types as $type => $config) {
            if ('image' !== $config['type']) {
                continue;
            }
            $inherited = array_replace_recursive($policy, ['imagine_driver' => $defaultImagineDriver], array_intersect_key($defaults, $keys), array_intersect_key($config, $keys), array_intersect_key($config['defaults'] ?? [], $keys));
            $types[$type]['imagine_driver'] = $inherited['imagine_driver'];
            foreach (array_keys($policy) as $option) {
                $types[$type][$option] = $inherited[$option];
            }
            $types[$type]['upload_requirements']['animation'] ??= 'accepted';
            foreach ($config['versions'] as $version => $versionConfig) {
                if (isset($versionConfig['upload_requirements'])) {
                    $types[$type]['versions'][$version]['upload_requirements']['animation'] ??= 'accepted';
                    continue;
                }
                $effective = array_replace_recursive($inherited, $versionConfig);
                $format = $effective['type'] = $versionConfig['type'] ?? 'keep';
                foreach ($qualities as $option => $formats) {
                    if (!in_array($format, $formats, true)) {
                        unset($effective[$option]);
                    }
                }
                if (in_array($format, ['avif', 'webp', 'png', 'apng', 'gif', 'keep'], true)) {
                    $effective['animated'] ??= 'keep';
                    if ('no' === $effective['animated']) {
                        unset($effective['animation']);
                    }
                } else {
                    unset($effective['animated'], $effective['animation']);
                }
                $effective['resampling-filter'] ??= ImageInterface::FILTER_LANCZOS;
                $effective['resolution-units'] ??= ImageInterface::RESOLUTION_PIXELSPERINCH;
                $types[$type]['versions'][$version] = $effective;
            }
        }

        return $types;
    }
}
