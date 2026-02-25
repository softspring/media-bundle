<?php

namespace Softspring\MediaBundle\Type;

interface MediaTypeProviderInterface
{
    public function getTypes(): array;

    public static function getPriority(): int;
}
