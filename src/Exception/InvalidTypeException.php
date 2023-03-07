<?php

namespace Softspring\MediaBundle\Exception;

class InvalidTypeException extends \Exception
{
    public function __construct(string $type, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Invalid "%s" media type. Check your configuration', $type), $code, $previous);
    }
}
