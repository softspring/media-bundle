<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Type;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 0)]
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

    public static function getPriority(): int
    {
        return 0;
    }
}
