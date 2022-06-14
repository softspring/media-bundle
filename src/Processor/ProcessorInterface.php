<?php

namespace Softspring\MediaBundle\Processor;

use Softspring\MediaBundle\Model\MediaVersionInterface;

interface ProcessorInterface
{
    public function supports(MediaVersionInterface $version): bool;

    public function process(MediaVersionInterface $version): void;

    public static function getPriority(): int;
}
