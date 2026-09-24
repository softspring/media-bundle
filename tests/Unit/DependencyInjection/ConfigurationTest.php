<?php

namespace Softspring\MediaBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\DependencyInjection\Configuration;
use Softspring\MediaBundle\Media\DefaultNameGenerator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigurationTest extends TestCase
{
    public function testBasicRequiredConfig(): void
    {
        $configs = [
            'sfs_media' => [
            ],
        ];
        $expected = [
            'filesystem' => [
                'path' => '%kernel.project_dir%/public/media',
                'url' => '/media',
            ],
            'entity_manager' => 'default',
            'driver' => 'filesystem',
            'ffmpeg' => [
                'binary' => 'ffmpeg',
                'probe_binary' => 'ffprobe',
                'timeout' => 300,
            ],
            'media' => [
                'class' => 'Softspring\MediaBundle\Entity\Media',
                'find_field_name' => 'id',
                'admin_controller' => true,
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

    public function testAdvancedConfig(): void
    {
        $configs = [
            'sfs_media' => [
                'entity_manager' => 'other_em',
                'media' => [
                    'class' => 'App\Entity\Media',
                    'find_field_name' => 'identificator',
                    'admin_controller' => false,
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
            'filesystem' => [
                'path' => '%kernel.project_dir%/public/media',
                'url' => '/media',
            ],
            'entity_manager' => 'other_em',
            'driver' => 'filesystem',
            'ffmpeg' => [
                'binary' => 'ffmpeg',
                'probe_binary' => 'ffprobe',
                'timeout' => 300,
            ],
            'media' => [
                'class' => 'App\Entity\Media',
                'find_field_name' => 'identificator',
                'admin_controller' => false,
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
                            'from' => '_original'
                        ],
                    ],
                    'pictures' => [
                    ],
                    'video_sets' => [
                    ],
                    'private' => false,
                ],
            ],
        ];

        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);
        $config['types'] = Configuration::fixConfigTypes($config['types'] ?? null);
        $this->assertEquals($expected, $config);
    }

    public function testGoogleCloudStorageConfigWithoutPublicBaseUrl(): void
    {
        $configs = [
            'sfs_media' => [
                'google_cloud_storage' => [
                    'bucket' => 'media-bucket',
                ],
            ],
        ];

        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);

        $this->assertSame('google_cloud_storage', $config['driver']);
        $this->assertSame('media-bucket', $config['google_cloud_storage']['bucket']);
        $this->assertNull($config['google_cloud_storage']['public_base_url']);
        $this->assertNull($config['google_cloud_storage']['delayed_deletion_days']);
    }

    public function testGoogleCloudStorageConfigWithDelayedDeletion(): void
    {
        $configs = [
            'sfs_media' => [
                'google_cloud_storage' => [
                    'bucket' => 'media-bucket',
                    'delayed_deletion_days' => 90,
                ],
            ],
        ];

        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);

        $this->assertSame(90, $config['google_cloud_storage']['delayed_deletion_days']);
    }

    public function testAnimatedVersionConfig(): void
    {
        $configs = [
            'sfs_media' => [
                'ffmpeg' => [
                    'binary' => '/usr/local/bin/ffmpeg',
                    'probe_binary' => '/usr/local/bin/ffprobe',
                    'timeout' => 600,
                ],
                'types' => [
                    'animation' => [
                        'upload_requirements' => [
                            'mimeTypes' => ['image/gif'],
                        ],
                        'versions' => [
                            'small' => [
                                'type' => 'avif',
                                'animated' => true,
                                'scale_width' => 320,
                                'avif_quality' => 82,
                                'animation' => [
                                    'fps' => 25,
                                    'loop' => 0,
                                    'crf' => 18,
                                    'speed' => 6,
                                    'keyframe_interval' => 25,
                                    'max_duration' => 3.0,
                                    'max_frames' => 75,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $processor = new Processor();
        $config = $processor->processConfiguration(new FfmpegAvailableConfiguration(), $configs);

        $this->assertSame('/usr/local/bin/ffmpeg', $config['ffmpeg']['binary']);
        $this->assertSame('/usr/local/bin/ffprobe', $config['ffmpeg']['probe_binary']);
        $this->assertSame(600, $config['ffmpeg']['timeout']);
        $this->assertTrue($config['types']['animation']['versions']['small']['animated']);
        $this->assertSame(75, $config['types']['animation']['versions']['small']['animation']['max_frames']);
    }

    public function testAnimatedVersionRequiresFfmpegAndFfprobeBinaries(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('ffmpeg (ffmpeg), ffprobe (ffprobe)');

        (new Processor())->processConfiguration(new FfmpegUnavailableConfiguration(), [
            'sfs_media' => [
                'types' => [
                    'animation' => [
                        'versions' => [
                            'small' => [
                                'type' => 'webp',
                                'animated' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testStaticTypesDoNotRequireFfmpegBinaries(): void
    {
        $config = (new Processor())->processConfiguration(new FfmpegUnavailableConfiguration(), [
            'sfs_media' => [
                'types' => [
                    'static' => [],
                ],
            ],
        ]);

        $this->assertArrayHasKey('static', $config['types']);
    }
}

class FfmpegAvailableConfiguration extends Configuration
{
    protected function isExecutableAvailable(string $binary): bool
    {
        return true;
    }
}

class FfmpegUnavailableConfiguration extends Configuration
{
    protected function isExecutableAvailable(string $binary): bool
    {
        return false;
    }
}
