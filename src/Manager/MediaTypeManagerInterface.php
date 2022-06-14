<?php

namespace Softspring\MediaBundle\Manager;

interface MediaTypeManagerInterface
{
    public function getTypes(): array;

    public function getType(string $type): ?array;
}
