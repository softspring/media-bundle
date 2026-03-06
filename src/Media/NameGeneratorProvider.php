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
        foreach ($nameGenerators as $generator) {
            $this->nameGenerators[get_class($generator)] = $generator;
        }
    }

    public function getGenerator(string $name): ?NameGeneratorInterface
    {
        return $this->nameGenerators[$name];
    }
}
