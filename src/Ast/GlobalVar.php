<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * WebAssembly module global variable definition.
 */
final class GlobalVar
{
    /**
     * @param GlobalType $type Global variable type and mutability
     * @param list<Instruction> $initExpr Initializer expression bytecode instructions
     */
    public function __construct(
        public readonly GlobalType $type,
        public readonly array $initExpr,
    ) {
    }
}
