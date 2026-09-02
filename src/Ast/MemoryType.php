<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * WebAssembly linear memory type (page limits).
 */
final class MemoryType
{
    public function __construct(
        public readonly Limits $limits,
    ) {
    }
}
