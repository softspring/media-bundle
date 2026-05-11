<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Type;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Type\ConfigMediaTypeProvider;

class ConfigMediaTypeProviderTest extends TestCase
{
    public function testReturnsConfiguredTypes(): void
    {
        $types = [
            'article_image' => [
                'type' => 'image',
                'name' => 'Article image',
            ],
        ];

        $provider = new ConfigMediaTypeProvider($types);

        $this->assertSame($types, $provider->getTypes());
    }

    public function testPriority(): void
    {
        $this->assertSame(0, ConfigMediaTypeProvider::getPriority());
    }
}
