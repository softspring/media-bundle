<?php

namespace Softspring\MediaBundle\Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Helper\TypeChecker;

class TypeCheckerTest extends TestCase
{
    public function testCheckMedia()
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

        $originalVersion = new MediaVersion('_original', $media);

        $versionOk = new MediaVersion('v1', $media);
        $versionOk->setOptions(['scale_width' => 100]);

        $versionDeleted = new MediaVersion('v100', $media);

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
}
