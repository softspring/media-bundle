<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Media\NameGeneratorInterface;
use Softspring\MediaBundle\Media\NameGeneratorProvider;
use Softspring\MediaBundle\Model\MediaInterface;
use Symfony\Component\HttpFoundation\File\File;

class NameGeneratorProviderTest extends TestCase
{
    public function testReturnsGeneratorsByClassName(): void
    {
        $generator = new class implements NameGeneratorInterface {
            public function generateName(MediaInterface $media, string $version, File $file): string
            {
                return 'generated-name';
            }

            public static function getPriority(): int
            {
                return 0;
            }
        };

        $provider = new NameGeneratorProvider([$generator]);

        $this->assertSame($generator, $provider->getGenerator(get_class($generator)));
    }
}
