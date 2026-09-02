<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * WebAssembly table type (element reference type and limits).
 */
final class TableType
{
    public function __construct(
        public readonly ValueType $elemType,
        public readonly Limits $limits,
    ) {
    }
}
