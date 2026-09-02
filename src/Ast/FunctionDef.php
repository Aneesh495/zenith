<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * WebAssembly function definition containing locals and executable bytecode instructions.
 */
final class FunctionDef
{
    /**
     * @param int $typeIndex Type signature index in module types table
     * @param list<ValueType> $locals Declared local variable types
     * @param list<Instruction> $body Instruction sequence comprising function body
     * @param string|null $debugName Optional symbolic identifier for stack traces and disassembly
     * @param array<int, string> $localNames Map of local variable index to debug identifier
     */
    public function __construct(
        public readonly int $typeIndex,
        public readonly array $locals = [],
        public array $body = [],
        public ?string $debugName = null,
        public array $localNames = [],
    ) {
    }
}
