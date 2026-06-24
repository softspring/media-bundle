<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\DependencyInjection\SfsMediaExtension;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SfsMediaExtensionTest extends TestCase
{
    public function testLoadsFilesystemConfiguration(): void
    {
        $container = new ContainerBuilder();

        (new SfsMediaExtension())->load([[
            'entity_manager' => 'custom',
            'media' => [
                'class' => Media::class,
                'find_field_name' => 'uuid',
                'admin_controller' => false,
            ],
            'version' => [
                'class' => MediaVersion::class,
                'find_field_name' => 'uuid',
            ],
            'driver' => 'filesystem',
            'filesystem' => [
                'path' => '/tmp/media',
                'url' => '/uploads',
            ],
            'types' => [
                'image' => [
                    'type' => 'image',
                    'upload_requirements' => [
                        'mimeTypes' => ['image/png'],
                    ],
                    'versions' => [],
                ],
            ],
        ]], $container);

        $this->assertSame('custom', $container->getParameter('sfs_media.entity_manager_name'));
        $this->assertSame(Media::class, $container->getParameter('sfs_media.media.class'));
        $this->assertSame('uuid', $container->getParameter('sfs_media.media.find_field_name'));
        $this->assertSame(MediaVersion::class, $container->getParameter('sfs_media.version.class'));
        $this->assertSame('uuid', $container->getParameter('sfs_media.version.find_field_name'));
        $this->assertSame('/tmp/media', $container->getParameter('sfs_media.storage.filesystem.path'));
        $this->assertSame('/uploads', $container->getParameter('sfs_media.storage.filesystem.url'));
        $this->assertArrayHasKey('image', $container->getParameter('sfs_media.types'));
        $this->assertFalse($container->hasDefinition('sfs_media.admin.media.list_controller'));
    }

    public function testLoadsGoogleCloudStorageConfiguration(): void
    {
        $container = new ContainerBuilder();

        (new SfsMediaExtension())->load([[
            'driver' => 'google_cloud_storage',
            'google_cloud_storage' => [
                'bucket' => 'media-bucket',
            ],
        ]], $container);

        $this->assertSame('media-bucket', $container->getParameter('sfs_media.storage.google_cloud_storage.bucket'));
        $this->assertNull($container->getParameter('sfs_media.storage.filesystem.path'));
        $this->assertNull($container->getParameter('sfs_media.storage.filesystem.url'));
    }

    public function testPrependsIntegrationConfiguration(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'App\Migrations' => '%kernel.project_dir%/migrations',
            ],
        ]);

        (new SfsMediaExtension())->prepend($container);

        $doctrineConfig = $container->getExtensionConfig('doctrine')[0];
        $this->assertSame(Media::class, $doctrineConfig['orm']['resolve_target_entities'][MediaInterface::class]);
        $this->assertSame(MediaVersion::class, $doctrineConfig['orm']['resolve_target_entities'][MediaVersionInterface::class]);
        $this->assertFalse($doctrineConfig['orm']['mappings']['SfsMediaBundle']['mapping']);

        $twigConfig = $container->getExtensionConfig('twig')[0];
        $this->assertArrayHasKey('version', $twigConfig['globals']['sfs_media_bundle']);
        $this->assertArrayHasKey('version_branch', $twigConfig['globals']['sfs_media_bundle']);

        $migrationsConfig = $container->getExtensionConfig('doctrine_migrations')[0];
        $this->assertSame('%kernel.project_dir%/migrations', $migrationsConfig['migrations_paths']['App\Migrations']);
        $this->assertSame('@SfsMediaBundle/src/Migrations', $migrationsConfig['migrations_paths']['Softspring\MediaBundle\Migrations']);

        if (interface_exists(AssetMapperInterface::class)) {
            $frameworkConfig = $container->getExtensionConfig('framework')[0];
            $this->assertSame('@softspring/media-bundle', $frameworkConfig['asset_mapper']['paths'][\dirname(__DIR__, 3).'/assets/dist']);
        } else {
            $this->assertSame([], $container->getExtensionConfig('framework'));
        }
    }
}
