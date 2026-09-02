<?php

declare(strict_types=1);

namespace Zenith\Runtime;

/**
 * Representation of a structured control flow label in the VM call frame.
 */
final class Label
{
    /**
     * @param int $instructionPointer Bytecode instruction pointer to resume execution
     * @param int $arity Number of result values expected on stack
     * @param int $stackHeight Operand stack height upon entering the block
     * @param bool $isLoop Whether this label is a loop header
     */
    public function __construct(
        public readonly int $instructionPointer,
        public readonly int $arity,
        public readonly int $stackHeight,
        public readonly bool $isLoop = false,
    ) {
    }
}
