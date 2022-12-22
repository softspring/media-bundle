<?php

namespace Softspring\MediaBundle\DependencyInjection;

use Imagine\Image\ImageInterface;
use Softspring\MediaBundle\Media\DefaultNameGenerator;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
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

        function_exists('imagegif') && function_exists('imagecreatefromgif') && ($supportedTypes['versionTypeExtensions'][] = 'gif') && ($supportedTypes['mime'][] = 'image/gif');
        function_exists('imagejpeg') && function_exists('imagecreatefromjpeg') && ($supportedTypes['versionTypeExtensions'][] = 'jpeg') && ($supportedTypes['mime'][] = 'image/jpeg');
        function_exists('imagewebp') && function_exists('imagecreatefromwebp') && ($supportedTypes['versionTypeExtensions'][] = 'webp') && ($supportedTypes['mime'][] = 'image/webp');
        function_exists('imagepng') && function_exists('imagecreatefrompng') && ($supportedTypes['versionTypeExtensions'][] = 'png') && ($supportedTypes['mime'][] = 'image/png');

        return $supportedTypes;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sfs_media');
        $rootNode = $treeBuilder->getRootNode();

        $supportedMimeTypes = $this->getSupportedMimeTypes();

        $rootNode
            ->validate()
                ->ifTrue(function ($config) {
                    return 'google_cloud_storage' === $config['driver'] && empty($config['google_cloud_storage']);
                })
                ->thenInvalid('google_cloud_storage config block is required when driver is google_cloud_storage.')
            ->end()

            ->children()
                ->scalarNode('entity_manager')
                    ->defaultValue('default')
                ->end()

                ->enumNode('driver')
                    ->defaultValue('google_cloud_storage')
                    ->values(['google_cloud_storage'])
                ->end()

                ->arrayNode('google_cloud_storage')
                    ->children()
                        ->scalarNode('bucket')->isRequired()->end()
                    ->end()
                ->end()

                ->arrayNode('media')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')->defaultValue('Softspring\MediaBundle\Entity\Media')->end()
                        ->scalarNode('find_field_name')->defaultValue('id')->end()
                        ->booleanNode('admin_controller')->defaultFalse()->end()
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
                            ->ifTrue(function ($config) use ($supportedMimeTypes) {
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
                            ->ifTrue(function ($config) use ($supportedMimeTypes) {
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
                            ->ifTrue(function ($config) use ($supportedMimeTypes) {
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
                            ->ifTrue(function ($config) { /* use ($supportedMimeTypes) */
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
                            ->scalarNode('description')->end()
                            ->scalarNode('generator')->defaultValue(DefaultNameGenerator::class)->end()
                            ->append($this->getUploadRequirementsNode())
                            ->append($this->getVersionsNode())
                            ->append($this->getPicturesNode())
                            ->append($this->getVideoSourcessNode())
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    public function getUploadRequirementsNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('upload_requirements');

        /** @var ArrayNodeDefinition $connectionNode */
        $node = method_exists(TreeBuilder::class, 'getRootNode') ? $treeBuilder->getRootNode() : $treeBuilder->root('upload_requirements');

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

        /** @var ArrayNodeDefinition $connectionNode */
        $node = method_exists(TreeBuilder::class, 'getRootNode') ? $treeBuilder->getRootNode() : $treeBuilder->root('versions');

        $node
            ->arrayPrototype()
                ->normalizeKeys(false)
                ->children()
                    ->append($this->getUploadRequirementsNode())
                    ->enumNode('type')->values(['jpeg', 'png', 'webp'])->end()
                    ->integerNode('scale_width')->end()
                    ->integerNode('scale_height')->end()
                    ->integerNode('png_compression_level')->end()
                    ->integerNode('webp_quality')->end()
                    ->integerNode('jpeg_quality')->end()
                    ->integerNode('webp_quality')->end()
                    ->booleanNode('flatten')->end()
                    ->integerNode('resolution-x')->end()
                    ->integerNode('resolution-y')->end()
                    ->scalarNode('resampling-filter')->end()
                    ->scalarNode('resolution-units')->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    public function getPicturesNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('pictures');

        /** @var ArrayNodeDefinition $connectionNode */
        $node = method_exists(TreeBuilder::class, 'getRootNode') ? $treeBuilder->getRootNode() : $treeBuilder->root('pictures');

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

    public function getVideoSourcessNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('video_sources');

        /** @var ArrayNodeDefinition $connectionNode */
        $node = method_exists(TreeBuilder::class, 'getRootNode') ? $treeBuilder->getRootNode() : $treeBuilder->root('video_sources');

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
                        empty($versionConfig['type']) && $types[$type]['versions'][$version]['type'] = 'jpeg'; // default jpeg
                        empty($versionConfig['resampling-filter']) && $types[$type]['versions'][$version]['resampling-filter'] = ImageInterface::FILTER_LANCZOS;
                        empty($versionConfig['resolution-units']) && $types[$type]['versions'][$version]['resolution-units'] = ImageInterface::RESOLUTION_PIXELSPERINCH;
                    }
                }
            }
        }

        return $types;
    }
}
