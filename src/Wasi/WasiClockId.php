<?php

declare(strict_types=1);

namespace Zenith\Wasi;

/**
 * Standard WASI Preview 1 clock identifiers.
 */
final class WasiClockId
{
    public const REALTIME = 0;
    public const MONOTONIC = 1;
    public const PROCESS_CPUTIME_ID = 2;
    public const THREAD_CPUTIME_ID = 3;
}
