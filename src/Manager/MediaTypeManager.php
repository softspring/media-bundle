<?php

namespace Softspring\MediaBundle\Manager;

use Softspring\MediaBundle\Type\MediaTypesCollection;

class MediaTypeManager implements MediaTypeManagerInterface
{
    protected MediaTypesCollection $mediaTypesCollection;

    public function __construct(MediaTypesCollection $mediaTypesCollection)
    {
        $this->mediaTypesCollection = $mediaTypesCollection;
    }

    public function getTypes(): array
    {
        return $this->mediaTypesCollection->getTypes();
    }

    public function getType(string $type): ?array
    {
        return $this->mediaTypesCollection->getType($type);
    }
}
