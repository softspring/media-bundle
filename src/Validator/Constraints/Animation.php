<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Validator\Constraints;

use InvalidArgumentException;
use Symfony\Component\Validator\Constraint;

class Animation extends Constraint
{
    public string $notAllowedMessage = 'Animated images are not accepted for this upload.';
    public string $requiredMessage = 'An animated image is required for this upload.';
    public string $inspectionFailedMessage = 'The image animation could not be inspected.';

    public function __construct(public string $policy = 'accepted', ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(groups: $groups, payload: $payload);
        if (!in_array($policy, ['no', 'accepted', 'required'], true)) {
            throw new InvalidArgumentException('Animation policy must be no, accepted or required.');
        }
    }
}
