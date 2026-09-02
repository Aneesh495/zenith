<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * WebAssembly module table definition.
 */
final class Table
{
    public function __construct(
        public readonly TableType $type,
    ) {
    }
}
