<?php

namespace Softspring\MediaBundle\Media;

class NameGenerators
{
    /**
     * @var NameGeneratorInterface[]
     */
    protected array $nameGenerators;

    public function __construct(array $nameGenerators = [])
    {
        $this->nameGenerators = $nameGenerators;
    }

    public function getGenerator(string $name): ?NameGeneratorInterface
    {
        return $this->nameGenerators[$name];
    }
}
