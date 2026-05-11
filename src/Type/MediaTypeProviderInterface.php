<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Type;

interface MediaTypeProviderInterface
{
    public function getTypes(): array;

    public static function getPriority(): int;
}
