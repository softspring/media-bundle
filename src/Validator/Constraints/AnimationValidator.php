<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Validator\Constraints;

use Softspring\MediaBundle\Tools\AnimationDetector;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Throwable;

class AnimationValidator extends ConstraintValidator
{
    public function __construct(private readonly AnimationDetector $animationDetector = new AnimationDetector())
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Animation) {
            throw new UnexpectedTypeException($constraint, Animation::class);
        }
        if ('accepted' === $constraint->policy || !$value instanceof File || !is_file($value->getPathname())) {
            return;
        }
        try {
            $animated = $this->animationDetector->isAnimated($value->getRealPath());
        } catch (Throwable) {
            $this->context->buildViolation($constraint->inspectionFailedMessage)->addViolation();

            return;
        }
        if ($animated && 'no' === $constraint->policy) {
            $this->context->buildViolation($constraint->notAllowedMessage)->addViolation();
        } elseif (!$animated && 'required' === $constraint->policy) {
            $this->context->buildViolation($constraint->requiredMessage)->addViolation();
        }
    }
}
