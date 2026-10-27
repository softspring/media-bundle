<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Validator;

use Softspring\MediaBundle\Tests\Fixtures\AnimationFixtures;
use Softspring\MediaBundle\Validator\Constraints\Animation;
use Softspring\MediaBundle\Validator\Constraints\AnimationValidator;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class AnimationValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new AnimationValidator();
    }

    public function testRejectsAnimationWhenDisallowed(): void
    {
        $this->checkPolicy(2, 'no', 'Animated images are not accepted for this upload.');
    }

    public function testRequiresAnimationWhenRequested(): void
    {
        $this->checkPolicy(1, 'required', 'An animated image is required for this upload.');
    }

    public function testAcceptsStaticImagesByDefault(): void
    {
        $this->checkPolicy(1, 'accepted');
    }

    public function testAcceptsAnimationByDefault(): void
    {
        $this->checkPolicy(2, 'accepted');
    }

    public function testAcceptsRequiredAnimation(): void
    {
        $this->checkPolicy(2, 'required');
    }

    public function testAcceptsDisallowedAnimationWhenFileIsStatic(): void
    {
        $this->checkPolicy(1, 'no');
    }

    private function checkPolicy(int $frames, string $policy, ?string $message = null): void
    {
        $path = tempnam(sys_get_temp_dir(), 'animation-');
        try {
            file_put_contents($path, AnimationFixtures::png($frames));
            $this->validator->validate(new File($path), new Animation($policy));
            if (null === $message) {
                $this->assertNoViolation();
            } else {
                $this->buildViolation($message)->assertRaised();
            }
        } finally {
            unlink($path);
        }
    }
}
