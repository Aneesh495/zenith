<?php

declare(strict_types=1);

namespace Zenith\Runtime;

use Zenith\Ast\FunctionDef;
use Zenith\Ast\FuncType;

/**
 * WebAssembly function bound to a module instance.
 */
final class WasmFunction
{
    public function __construct(
        public readonly FuncType $type,
        public readonly FunctionDef $def,
        public readonly Instance $instance,
    ) {
    }
}
