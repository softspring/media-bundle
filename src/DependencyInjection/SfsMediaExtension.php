<?php

namespace Softspring\MediaBundle\DependencyInjection;

use Imagine\Image\ImageInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SfsMediaExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config/services'));

        // set config parameters
        $container->setParameter('sfs_media.entity_manager_name', $config['entity_manager']);

        // configure model classes
        $container->setParameter('sfs_media.media.class', $config['media']['class']);
        $container->setParameter('sfs_media.media.find_field_name', $config['media']['find_field_name'] ?? null);
        $container->setParameter('sfs_media.version.class', $config['version']['class']);
        $container->setParameter('sfs_media.version.find_field_name', $config['version']['find_field_name'] ?? null);
        $container->setParameter('sfs_media.types', $this->fixConfigTypes($config['types'] ?? null));

        // load services
        $loader->load('services.yaml');

        if ($config['media']['admin_controller']) {
            $loader->load('controller/admin_medias.yaml');
        }

        if ('google_cloud_storage' === $config['driver']) {
            $container->setParameter('sfs_media.storage.google_cloud_storage.bucket', $config['google_cloud_storage']['bucket']);
            $loader->load('drivers/google_cloud_storage.yaml');
        }
    }

    /**
     * Update config types, this can not be done in processors because it would be not used to compare with database versions.
     * Also, can not be set in configuration as default values, because is exclusive for some types.
     */
    protected function fixConfigTypes(?array $types = null): ?array
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

    public function prepend(ContainerBuilder $container)
    {
        $doctrineConfig = [];

        // add a default config to force load target_entities, will be overwritten by ResolveDoctrineTargetEntityPass
        $doctrineConfig['orm']['resolve_target_entities'][MediaInterface::class] = 'Softspring\MediaBundle\Entity\Media';
        $doctrineConfig['orm']['resolve_target_entities'][MediaVersionInterface::class] = 'Softspring\MediaBundle\Entity\MediaVersion';

        // disable auto-mapping for this bundle to prevent mapping errors
        $doctrineConfig['orm']['mappings']['SfsMediaBundle'] = [
            'is_bundle' => true,
            'mapping' => false,
        ];

        $container->prependExtensionConfig('doctrine', $doctrineConfig);
    }
}
