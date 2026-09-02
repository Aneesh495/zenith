<?php

declare(strict_types=1);

namespace Zenith\Wasi;

use RuntimeException;

/**
 * Exception raised when proc_exit is invoked by WASI.
 */
class WasiExitException extends RuntimeException
{
    public function __construct(
        public readonly int $exitCode
    ) {
        parent::__construct("WASI process terminated with exit code {$exitCode}", $exitCode);
    }
}
