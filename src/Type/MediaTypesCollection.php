<?php

namespace Softspring\MediaBundle\Type;

use Softspring\MediaBundle\Exception\InvalidTypeException;

class MediaTypesCollection
{
    protected array $types = [];

    /**
     * @param MediaTypeProviderInterface[] $typeProviders
     */
    public function __construct(array $typeProviders)
    {
        foreach ($typeProviders as $provider) {
            $this->types += $provider->getTypes();
        }
    }

    public function getTypes(?bool $private = null): array
    {
        if (is_bool($private)) {
            return array_filter($this->types, function (array $type) use ($private): bool {
                return $type['private'] === $private;
            });
        }

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
