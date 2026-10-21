<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Helper\TypeChecker;

class TypeCheckerTest extends TestCase
{
    public function testCheckMedia(): void
    {
        $media = new Media();

        $typeConfig = [
            'versions' => [
                'v1' => ['scale_width' => 100],
                'v2' => ['scale_width' => 100, 'scale_height' => 200],
                'v3' => ['scale_width' => 100],
                'v4' => ['upload_requirements' => ['mimeType' => ['image/jpeg']]],
            ],
        ];

        new MediaVersion('_original', $media);

        $versionOk = new MediaVersion('v1', $media);
        $versionOk->setOptions(['scale_width' => 100]);

        new MediaVersion('v100', $media);

        $versionChanged = new MediaVersion('v2', $media);
        $versionChanged->setOptions(['scale_width' => 150]);

        $result = TypeChecker::checkMedia($media, $typeConfig);

        $this->assertEquals([
            'new' => [
                'v3',
            ],
            'ok' => [
                '_original',
                'v1',
            ],
            'changed' => [
                'v2' => [
                    'scale_width' => [
                        'config' => 100,
                        'db' => 150,
                        'string' => 'scale_width: 150 => 100',
                    ],
                    'scale_height' => [
                        'config' => 200,
                        'db' => null,
                        'string' => 'scale_height: null => 200',
                    ],
                ],
            ],
            'delete' => [
                'v100',
            ],
            'manual' => [
                'v4',
            ],
        ], $result);
    }

    public function testNestedOptionsAreComparedIndependentOfAssociativeKeyOrder(): void
    {
        $media = new Media();
        $version = new MediaVersion('animated', $media);
        $version->setOptions([
            'animation' => [
                'max_frames' => 240,
                'max_duration' => 10,
            ],
        ]);

        $result = TypeChecker::checkMedia($media, [
            'versions' => [
                'animated' => [
                    'animation' => [
                        'max_duration' => 10,
                        'max_frames' => 240,
                    ],
                ],
            ],
        ]);

        $this->assertSame(['animated'], $result['ok']);
        $this->assertSame([], $result['changed']);
    }

    public function testVersionWithNullOptionsIsValid(): void
    {
        $media = new Media();
        new MediaVersion('poster', $media);

        $result = TypeChecker::checkMedia($media, [
            'versions' => [
                'poster' => [
                    'upload_requirements' => [
                        'mimeType' => ['image/jpeg'],
                    ],
                ],
            ],
        ]);

        $this->assertSame(['poster'], $result['ok']);
        $this->assertSame([], $result['changed']);
    }

    public function testNestedChangedOptionsHaveAReadableDescription(): void
    {
        $media = new Media();
        $version = new MediaVersion('animated', $media);
        $version->setOptions([
            'animation' => [
                'max_frames' => 240,
                'max_duration' => 12,
            ],
        ]);

        $result = TypeChecker::checkMedia($media, [
            'versions' => [
                'animated' => [
                    'animation' => [
                        'max_duration' => 10,
                        'max_frames' => 240,
                    ],
                ],
            ],
        ]);

        $this->assertSame(
            'animation: {"max_duration":12,"max_frames":240} => {"max_duration":10,"max_frames":240}',
            $result['changed']['animated']['animation']['string'],
        );
    }

    public function testAddingChangingAndRemovingDriverOptionsTriggersRegeneration(): void
    {
        foreach ([[[], ['imagine_driver' => 'imagick']], [['imagine_driver' => 'gd'], ['imagine_driver' => 'imagick']], [['imagine_driver' => 'imagick'], []]] as [$stored, $configured]) {
            $media = new Media();
            $version = new MediaVersion('large', $media);
            $version->setOptions($stored);
            $result = TypeChecker::checkMedia($media, ['versions' => ['large' => $configured]]);
            self::assertArrayHasKey('imagine_driver', $result['changed']['large']);
        }
    }

    public function testPersistedEffectiveDriverDoesNotRegenerateAgain(): void
    {
        $media = new Media();
        $version = new MediaVersion('large', $media);
        $version->setOptions(['imagine_driver' => 'imagick']);
        $result = TypeChecker::checkMedia($media, ['versions' => ['large' => ['imagine_driver' => 'imagick']]]);
        self::assertSame(['large'], $result['ok']);
        self::assertSame([], $result['changed']);
    }
}
