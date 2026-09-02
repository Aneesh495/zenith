<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * WebAssembly module memory definition.
 */
final class Memory
{
    public function __construct(
        public readonly MemoryType $type,
    ) {
    }
}
