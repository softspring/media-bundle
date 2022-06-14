<?php

namespace Softspring\MediaBundle\EntityManager;

/**
 * @deprecated move to Type management (this is not an entity manager)
 */
interface MediaTypeManagerInterface
{
    public function getTypes(): array;

    public function getType(string $type): ?array;
}
