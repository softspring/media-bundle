<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Exception;

use Exception;
use Throwable;

class InvalidTypeException extends Exception
{
    public function __construct(string $type, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('Invalid "%s" media type. Check your configuration', $type), $code, $previous);
    }
}
