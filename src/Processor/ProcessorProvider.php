<?php

namespace Softspring\MediaBundle\Processor;

use Softspring\MediaBundle\Model\MediaVersionInterface;

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
        $this->processors = $processors instanceof \Traversable ? iterator_to_array($processors) : $processors;
    }

    /**
     * @return ProcessorInterface[]
     */
    public function getProcessors(MediaVersionInterface $version): array
    {
        return array_filter($this->processors, fn (ProcessorInterface $processor) => $processor->supports($version));
    }

    public function applyProcessors(MediaVersionInterface $version): void
    {
        foreach ($this->getProcessors($version) as $processor) {
            $processor->process($version);
        }
    }
}
