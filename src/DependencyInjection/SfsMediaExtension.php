<?php

namespace Softspring\MediaBundle\DependencyInjection;

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
        $container->setParameter('sfs_media.types', $config['types'] ?? null);

        // load services
        $loader->load('services.yaml');

        if ($config['media']['admin_controller']) {
            $loader->load('controller/admin_medias.yaml');
        }

        if ($config['driver'] === 'google_cloud_storage') {
            $container->setParameter('sfs_media.storage.google_cloud_storage.bucket', $config['google_cloud_storage']['bucket']);
            $loader->load('drivers/google_cloud_storage.yaml');
        }
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
