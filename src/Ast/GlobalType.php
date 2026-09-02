<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * WebAssembly global variable type (value type and mutability).
 */
final class GlobalType
{
    public function __construct(
        public readonly ValueType $valType,
        public readonly bool $mutable,
    ) {
    }
}
