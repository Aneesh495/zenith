<?php

declare(strict_types=1);

namespace Zenith\Ast;

use Zenith\Binary\Opcode;

/**
 * Representation of a single WebAssembly bytecode instruction and its immediates.
 */
final class Instruction
{
    /**
     * @param int $opcode Bytecode opcode identifier
     * @param mixed $immediate Immediate operand value (null, int, float, array, BlockType)
     * @param int $offset Byte offset in code section for diagnostics and debugging
     */
    public function __construct(
        public readonly int $opcode,
        public readonly mixed $immediate = null,
        public readonly int $offset = 0,
    ) {
    }

    public function is(int $opcode): bool
    {
        return $this->opcode === $opcode;
    }

    public function getMnemonic(): string
    {
        return Opcode::getMnemonic($this->opcode);
    }

    public function __toString(): string
    {
        $mnemonic = $this->getMnemonic();
        if ($this->immediate === null) {
            return $mnemonic;
        }

        if ($this->immediate instanceof BlockType) {
            if ($this->immediate->isEmpty) {
                return $mnemonic;
            }
            if ($this->immediate->valType !== null) {
                return $mnemonic . ' (result ' . $this->immediate->valType->name() . ')';
            }
            return $mnemonic . ' (type ' . $this->immediate->typeIndex . ')';
        }

        if (is_array($this->immediate)) {
            $parts = [];
            foreach ($this->immediate as $k => $v) {
                if (is_array($v)) {
                    $parts[] = '[' . implode(' ', $v) . ']';
                } else {
                    $parts[] = (string)$v;
                }
            }
            return $mnemonic . ' ' . implode(' ', $parts);
        }

        if (is_float($this->immediate)) {
            return sprintf('%s %g', $mnemonic, $this->immediate);
        }

        return $mnemonic . ' ' . (string)$this->immediate;
    }
}
