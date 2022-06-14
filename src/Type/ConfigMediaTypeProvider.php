<?php

namespace Softspring\MediaBundle\Type;

class ConfigMediaTypeProvider implements MediaTypeProviderInterface
{
    protected array $mediaTypesConfig;

    public function __construct(array $mediaTypesConfig)
    {
        $this->mediaTypesConfig = $mediaTypesConfig;
    }

    public function getTypes(): array
    {
        return $this->mediaTypesConfig;
    }
}
