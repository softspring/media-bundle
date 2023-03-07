<?php

namespace Softspring\MediaBundle\Type;

use Softspring\MediaBundle\Exception\InvalidTypeException;

class MediaTypesCollection
{
    protected array $types = [];

    /**
     * @param MediaTypeProviderInterface[] $providers
     */
    public function __construct(array $providers)
    {
        foreach ($providers as $provider) {
            $this->types += $provider->getTypes();
        }
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @throws InvalidTypeException
     */
    public function getType(string $type): array
    {
        if (!isset($this->types[$type])) {
            throw new InvalidTypeException($type);
        }

        return $this->types[$type];
    }
}
