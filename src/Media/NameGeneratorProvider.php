<?php

namespace Softspring\MediaBundle\Media;

class NameGeneratorProvider
{
    /**
     * @var NameGeneratorInterface[]
     */
    protected array $nameGenerators;

    public function __construct(iterable $nameGenerators = [])
    {
        $this->nameGenerators = (array) $nameGenerators;
    }

    public function getGenerator(string $name): ?NameGeneratorInterface
    {
        return $this->nameGenerators[$name];
    }
}
