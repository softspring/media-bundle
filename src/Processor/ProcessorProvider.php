<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Processor;

use Softspring\MediaBundle\Model\MediaVersionInterface;
use Traversable;

class ProcessorProvider
{
    /**
     * @var ProcessorInterface[]
     */
    protected array $processors;

    /**
     * @param ProcessorInterface[] $processors
     */
    public function __construct(iterable $processors)
    {
        $this->processors = $processors instanceof Traversable ? iterator_to_array($processors) : $processors;
    }

    /**
     * @return ProcessorInterface[]
     */
    public function getProcessors(MediaVersionInterface $version): array
    {
        return array_filter($this->processors, fn (ProcessorInterface $processor): bool => $processor->supports($version));
    }

    public function applyProcessors(MediaVersionInterface $version): void
    {
        $imageProcessed = false;
        foreach ($this->processors as $processor) {
            // File-copy processors must finish before an encoder inspects the
            // source. Do not re-encode an FFmpeg result through Imagine.
            if (($imageProcessed && $processor instanceof ImageProcessorInterface) || !$processor->supports($version)) {
                continue;
            }
            $processor->process($version);
            $imageProcessed = $imageProcessed || $processor instanceof ImageProcessorInterface;
        }
    }
}
