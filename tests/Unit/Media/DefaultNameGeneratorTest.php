<?php

namespace Softspring\MediaBundle\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Media\DefaultNameGenerator;
use Symfony\Component\HttpFoundation\File\File;

class DefaultNameGeneratorTest extends TestCase
{
    public function testGeneratesNameUsingMediaIdWhenAvailable(): void
    {
        $generator = new DefaultNameGenerator();
        $media = new Media();

        $reflectionClass = new ReflectionClass($media);
        $reflectionProperty = $reflectionClass->getProperty('id');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($media, 'media-id');

        $tempFile = tempnam(sys_get_temp_dir(), 'media-name-generator-');
        file_put_contents($tempFile, 'content');

        $name = $generator->generateName($media, 'card', new File($tempFile));

        $this->assertMatchesRegularExpression('#^media-id/[a-f0-9]{40}\.card\.[a-z0-9]+$#', $name);
    }

    public function testGeneratesOriginalNameWithoutVersionSuffix(): void
    {
        $generator = new DefaultNameGenerator();
        $media = new Media();

        $tempFile = tempnam(sys_get_temp_dir(), 'media-name-generator-');
        file_put_contents($tempFile, 'content');

        $name = $generator->generateName($media, '_original', new File($tempFile));

        $this->assertMatchesRegularExpression('#^[a-f0-9]{40}\.[a-z0-9]+$#', $name);
    }
}
