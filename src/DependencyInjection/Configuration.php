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
            ],
            'extension' => [
                'svg',
            ],
        ];

        function_exists('imagegif') && function_exists('imagecreatefromgif') && ($supportedTypes['extension'][] = 'gif') && ($supportedTypes['mime'][] = 'image/gif');
        function_exists('imagejpeg') && function_exists('imagecreatefromjpeg') && ($supportedTypes['extension'][] = 'jpeg') && ($supportedTypes['mime'][] = 'image/jpeg');
        function_exists('imagewebp') && function_exists('imagecreatefromwebp') && ($supportedTypes['extension'][] = 'webp') && ($supportedTypes['mime'][] = 'image/webp');
        function_exists('imagepng') && function_exists('imagecreatefrompng') && ($supportedTypes['extension'][] = 'png') && ($supportedTypes['mime'][] = 'image/png');

        return $supportedTypes;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sfs_media');
        $rootNode = $treeBuilder->getRootNode();

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
                            ->ifTrue(function ($config) {
                                $supportedMimeTypes = $this->getSupportedMimeTypes();
                                foreach ($config['upload_requirements']['mimeTypes'] ?? [] as $mimeType) {
                                    if (!in_array($mimeType, $supportedMimeTypes['mime'])) {
                                        return true;
                                    }
                                }

                                foreach ($config['versions'] as $version) {
                                    if (!in_array($version['type'], $supportedMimeTypes['extension'])) {
                                        return true;
                                    }
                                }

                                return false;
                            })
                            ->thenInvalid('Some configured formats are not supported. The allowed formats are: '.implode(', ', $this->getSupportedMimeTypes()['mime']).'. Maybe you need to install some libraries to support them.')
                        ->end()

                        ->children()
                            ->scalarNode('name')->end()
                            ->scalarNode('description')->end()
                            ->scalarNode('generator')->defaultValue(DefaultNameGenerator::class)->end()
                            ->append($this->getUploadRequirementsNode())
                            ->append($this->getVersionsNode())
                            ->append($this->getPicturesNode())
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
                ->booleanNode('allowSquare')->defaultTrue()->end()
                ->booleanNode('allowLandscape')->defaultTrue()->end()
                ->booleanNode('allowPortrait')->defaultTrue()->end()
                ->booleanNode('detectCorrupted')->defaultFalse()->end()
                ->arrayNode('mimeTypes')->scalarPrototype()->end()->end()
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
                    ->enumNode('type')->values(['jpeg', 'png', 'webp'])->defaultValue('jpeg')->end()  // TODO NOT DEFAULT VALUE
                    ->integerNode('scale_width')->end()
                    ->integerNode('scale_height')->end()
                    ->integerNode('png_compression_level')->end()
                    ->integerNode('webp_quality')->end()
                    ->integerNode('jpeg_quality')->end()
                    ->integerNode('webp_quality')->end()
                    ->booleanNode('flatten')->end()
                    ->integerNode('resolution-x')->end()
                    ->integerNode('resolution-y')->end()
                    ->scalarNode('resampling-filter')->defaultValue(ImageInterface::FILTER_LANCZOS)->end() // TODO DEFAULT ONLY FOR IMAGES
                    ->scalarNode('resolution-units')->defaultValue(ImageInterface::RESOLUTION_PIXELSPERINCH)->end() // TODO DEFAULT ONLY FOR IMAGES
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
}
