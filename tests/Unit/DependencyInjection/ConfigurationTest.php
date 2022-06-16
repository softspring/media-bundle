<?php

namespace Softspring\MediaBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\DependencyInjection\Configuration;
use Softspring\MediaBundle\Media\DefaultNameGenerator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testEmptyConfig()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "sfs_media": google_cloud_storage config block is required when driver is google_cloud_storage.');

        $configs = [];
        $expected = [];

        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);
        $this->assertEquals($expected, $config);
    }

    public function testBasicRequiredConfig()
    {
        $configs = [
            'sfs_media' => [
                'google_cloud_storage' => [
                    'bucket' => 'test-bucket',
                ],
            ],
        ];
        $expected = [
            'google_cloud_storage' => [
                'bucket' => 'test-bucket',
            ],
            'entity_manager' => 'default',
            'driver' => 'google_cloud_storage',
            'media' => [
                'class' => 'Softspring\MediaBundle\Entity\Media',
                'find_field_name' => 'id',
                'admin_controller' => false,
            ],
            'version' => [
                'class' => 'Softspring\MediaBundle\Entity\MediaVersion',
                'find_field_name' => 'id',
            ],
            'types' => [],
        ];

        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);
        $this->assertEquals($expected, $config);
    }

    public function testAdvancedConfig()
    {
        $configs = [
            'sfs_media' => [
                'google_cloud_storage' => [
                    'bucket' => 'test-bucket',
                ],
                'entity_manager' => 'other_em',
                'media' => [
                    'class' => 'App\Entity\Media',
                    'find_field_name' => 'identificator',
                    'admin_controller' => true,
                ],
                'version' => [
                    'class' => 'App\Entity\MediaVersion',
                    'find_field_name' => 'identificator',
                ],
                'types' => [
                    'background' => [
                        'name' => 'Background image',
                        'upload_requirements' => [
                            'minWidth' => 1280,
                            'minHeight' => 450,
                            'allowLandscape' => true,
                            'allowPortrait' => false,
                            'mimeTypes' => ['image/png', 'image/jpeg'],
                        ],
                        'versions' => [
                            '_thumbnail' => [
                                'type' => 'jpeg',
                                'scale_width' => 300,
                                'jpeg_quality' => 70,
                                'resolution-x' => 72,
                                'resolution-y' => 72,
                            ],
                        ],
                        'pictures' => [
                        ],
//            versions:
//                _thumbnail: { type: 'jpeg', scale_width: 300, jpeg_quality: 70, resolution-x: 72, resolution-y: 72 } # admin thumbnail
//                xs: { type: 'jpeg', scale_width: 360, jpeg_quality: 70, resolution-x: 72, resolution-y: 72 }
//                sm: { type: 'jpeg', scale_width: 768, jpeg_quality: 70, resolution-x: 72, resolution-y: 72 }
//                md: { type: 'jpeg', scale_width: 1024, jpeg_quality: 70, resolution-x: 72, resolution-y: 72 }
//                xl: { type: 'jpeg', scale_width: 1280, jpeg_quality: 70, resolution-x: 72, resolution-y: 72 }
//            pictures:
//                _default:
//                    sources:
//                        - { srcset: [{ version: sm, suffix: '1x' }, { version: xs, suffix: '2x' }], attrs: { media: "(min-width: 200w)" } }
//                        - { srcset: [{ version: sm }], attrs: { media: "(min-width: 500w)", sizes: "100vw" } }
//                        - { srcset: [{ version: xs }], attrs: { media: "(min-width: 200w)", sizes: "50vw" } }
//                    img:
//                        src_version: xl
                    ],
                ],
            ],
        ];
        $expected = [
            'google_cloud_storage' => [
                'bucket' => 'test-bucket',
            ],
            'entity_manager' => 'other_em',
            'driver' => 'google_cloud_storage',
            'media' => [
                'class' => 'App\Entity\Media',
                'find_field_name' => 'identificator',
                'admin_controller' => true,
            ],
            'version' => [
                'class' => 'App\Entity\MediaVersion',
                'find_field_name' => 'identificator',
            ],
            'types' => [
                'background' => [
                    'name' => 'Background image',
                    'type' => 'image',
                    'generator' => DefaultNameGenerator::class,
                    'upload_requirements' => [
                        'minWidth' => 1280,
                        'minHeight' => 450,
                        'allowLandscape' => true,
                        'allowPortrait' => false,
                        'mimeTypes' => ['image/png', 'image/jpeg'],
                    ],
                    'versions' => [
                        '_thumbnail' => [
                            'type' => 'jpeg',
                            'scale_width' => 300,
                            'jpeg_quality' => 70,
                            'resolution-x' => 72,
                            'resolution-y' => 72,
                            'resampling-filter' => 'lanczos',
                            'resolution-units' => 'ppi',
                        ],
                    ],
                    'pictures' => [
                    ],
                    'video_sources' => [
                    ],
                ],
            ],
        ];

        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);
        $config['types'] = Configuration::fixConfigTypes($config['types'] ?? null);
        $this->assertEquals($expected, $config);
    }
}
