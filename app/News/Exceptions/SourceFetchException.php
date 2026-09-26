<?php

namespace App\News\Exceptions;

use RuntimeException;

class SourceFetchException extends RuntimeException
{
    public function __construct(public readonly string $category, string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
