<?php

namespace Softspring\MediaBundle\DependencyInjection;

use Composer\InstalledVersions;
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
    public function load(array $configs, ContainerBuilder $container): void
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
        $container->setParameter('sfs_media.types', Configuration::fixConfigTypes($config['types'] ?? null));

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

    public function prepend(ContainerBuilder $container): void
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

        $version = InstalledVersions::getVersion('softspring/media-bundle');
        if (str_ends_with($version, '-dev')) {
            $version = InstalledVersions::getPrettyVersion('softspring/media-bundle');
        }
        $container->prependExtensionConfig('twig', [
            'globals' => [
                'sfs_media_bundle' => [
                    'version' => $version,
                    'version_branch' => str_ends_with($version, '-dev') ? str_replace('.x-dev', '', $version) : false,
                ],
            ],
        ]);

        $doctrineConfig = $container->getExtensionConfig('doctrine_migrations');
        $container->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => array_merge(array_pop($doctrineConfig)['migrations_paths'] ?? [], [
                'Softspring\MediaBundle\Migrations' => '@SfsMediaBundle/src/Migrations',
            ]),
        ]);
    }
}
