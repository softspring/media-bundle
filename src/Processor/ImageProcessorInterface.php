<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Processor;

/** At most one image encoder processes a version in each pipeline run. */
interface ImageProcessorInterface extends ProcessorInterface
{
}
