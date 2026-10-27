<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Validator;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Tests\Fixtures\AnimationFixtures;
use Softspring\MediaBundle\Validator\Constraints\Image;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validation;

final class ImageValidatorTest extends TestCase
{
    public function testApngMimeTypeAcceptsAnimationAndRejectsStaticPng(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'image-validator-');
        try {
            $validator = Validation::createValidator();
            foreach ([1, 2] as $frames) {
                file_put_contents($path, AnimationFixtures::png($frames));
                $file = new UploadedFile($path, 'image.png', null, null, true);
                $violations = $validator->validate($file, new Image(mimeTypes: ['image/apng']));
                self::assertCount(1 === $frames ? 1 : 0, $violations);
            }
        } finally {
            unlink($path);
        }
    }
}
