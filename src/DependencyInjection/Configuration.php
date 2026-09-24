<?php

namespace Softspring\MediaBundle\DependencyInjection;

use Imagine\Image\ImageInterface;
use Softspring\MediaBundle\Media\DefaultNameGenerator;
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
                'svg',
            ],
        ];

        if (function_exists('imagegif') && function_exists('imagecreatefromgif') && ($supportedTypes['versionTypeExtensions'][] = 'gif')) {
            $supportedTypes['mime'][] = 'image/gif';
        }
        if (function_exists('imagejpeg') && function_exists('imagecreatefromjpeg') && ($supportedTypes['versionTypeExtensions'][] = 'jpeg')) {
            $supportedTypes['mime'][] = 'image/jpeg';
        }
        if (function_exists('imagewebp') && function_exists('imagecreatefromwebp') && ($supportedTypes['versionTypeExtensions'][] = 'webp')) {
            $supportedTypes['mime'][] = 'image/webp';
        }
        if (function_exists('imageavif') && function_exists('imagecreatefromavif') && ($supportedTypes['versionTypeExtensions'][] = 'avif')) {
            $supportedTypes['mime'][] = 'image/avif';
        }
        if (function_exists('imagepng') && function_exists('imagecreatefrompng')) {
            $supportedTypes['versionTypeExtensions'][] = 'png';
            $supportedTypes['versionTypeExtensions'][] = 'apng';
            $supportedTypes['mime'][] = 'image/png';
            $supportedTypes['mime'][] = 'image/apng';
        }

        return $supportedTypes;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sfs_media');

        $rootNode = $treeBuilder->getRootNode();

        $supportedMimeTypes = $this->getSupportedMimeTypes();

        $rootNode
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

            ->children()
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
                    ->end()
                ->end()

                ->arrayNode('filesystem')
                    ->children()
                        ->scalarNode('path')->defaultValue('%kernel.project_dir%/public/media')->end()
                        ->scalarNode('url')->defaultValue('/media')->end()
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
                            ->enumNode('type')
                                ->values(['video', 'image'])
                                ->defaultValue('image')
                            ->end()
                            ->scalarNode('name')->end()
                            ->booleanNode('private')->defaultFalse()->end()
                            ->scalarNode('description')->end()
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

    private function hasAnimatedVersions(array $config): bool
    {
        foreach ($config['types'] ?? [] as $type) {
            foreach ($type['versions'] ?? [] as $version) {
                if ($version['animated'] ?? false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getUploadRequirementsNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('upload_requirements');

        $node = $treeBuilder->getRootNode();

        $node
            ->children()
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
                ->arrayNode('mimeTypes')->scalarPrototype()->end()->end()
                ->scalarNode('maxSize')->end()
                ->booleanNode('binaryFormat')->end()
            ->end()
        ;

        return $node;
    }

    public function getVersionsNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('versions');

        $node = $treeBuilder->getRootNode();

        $node
            ->arrayPrototype()
                ->normalizeKeys(false)
                ->validate()
                    ->ifTrue(function (array $version): bool {
                        if (empty($version['animated'])) {
                            return isset($version['animation']);
                        }

                        if (isset($version['upload_requirements'])) {
                            return true;
                        }

                        return isset($version['type']) && !in_array($version['type'], ['avif', 'webp', 'apng', 'keep'], true);
                    })
                    ->thenInvalid('Animated versions must be generated, use avif, webp, apng or keep, and set animation options only together with animated: true.')
                ->end()
                ->children()
                    ->append($this->getUploadRequirementsNode())
                    ->enumNode('type')->values(['jpeg', 'png', 'webp', 'keep', 'apng', 'avif'])->end()
                    ->booleanNode('animated')->end()
                    ->arrayNode('animation')
                        ->children()
                            ->integerNode('fps')->min(1)->max(120)->end()
                            ->integerNode('loop')->min(0)->max(65535)->end()
                            ->integerNode('crf')->min(0)->max(63)->end()
                            ->integerNode('speed')->min(0)->max(8)->end()
                            ->integerNode('keyframe_interval')->min(1)->end()
                            ->floatNode('max_duration')->min(0.001)->end()
                            ->integerNode('max_frames')->min(2)->end()
                        ->end()
                    ->end()
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
                        ->end()
                    ->end()
                    ->arrayNode('sources')
                        ->arrayPrototype()
                            ->children()
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
    public static function fixConfigTypes(?array $types = null): ?array
    {
        if (null === $types) {
            return null;
        }

        foreach ($types as $type => $config) {
            if ('image' === $config['type']) {
                foreach ($config['versions'] as $version => $versionConfig) {
                    if (!isset($versionConfig['upload_requirements'])) {
                        if (empty($versionConfig['type'])) {
                            $types[$type]['versions'][$version]['type'] = 'keep';
                        }
                        // default keep
                        if (empty($versionConfig['resampling-filter'])) {
                            $types[$type]['versions'][$version]['resampling-filter'] = ImageInterface::FILTER_LANCZOS;
                        }
                        if (empty($versionConfig['resolution-units'])) {
                            $types[$type]['versions'][$version]['resolution-units'] = ImageInterface::RESOLUTION_PIXELSPERINCH;
                        }
                    }
                }
            }
        }

        return $types;
    }
}
