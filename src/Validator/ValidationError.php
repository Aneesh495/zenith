<?php

declare(strict_types=1);

namespace Zenith\Validator;

use RuntimeException;

/**
 * Exception thrown when a WebAssembly module fails static type validation.
 */
class ValidationError extends RuntimeException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct('validation error: ' . $message, $code, $previous);
    }
}
