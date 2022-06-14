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
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sfs_media');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->validate()
                ->ifTrue(function ($config) {
                    return $config['driver'] === 'google_cloud_storage' && empty($config['google_cloud_storage']);
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
                    ->enumNode('type')->values(['jpeg', 'png', 'webp'])->defaultValue('jpeg')->end()
                    ->integerNode('scale_width')->end()
                    ->integerNode('scale_height')->end()
                    ->integerNode('png_compression_level')->end()
                    ->integerNode('webp_quality')->end()
                    ->integerNode('jpeg_quality')->end()
                    ->integerNode('webp_quality')->end()
                    ->booleanNode('flatten')->end()
                    ->integerNode('resolution-x')->end()
                    ->integerNode('resolution-y')->end()
                    ->scalarNode('resampling-filter')->defaultValue(ImageInterface::FILTER_LANCZOS)->end()
                    ->scalarNode('resolution-units')->defaultValue(ImageInterface::RESOLUTION_PIXELSPERINCH)->end()
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
