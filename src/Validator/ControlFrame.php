<?php

declare(strict_types=1);

namespace Zenith\Validator;

use Zenith\Ast\ValueType;
use Zenith\Binary\Opcode;

/**
 * Control stack frame used during static type validation.
 */
final class ControlFrame
{
    /**
     * @param int $opcode Block opcode (BLOCK, LOOP, IF)
     * @param list<ValueType> $startTypes Expected parameter types at entry
     * @param list<ValueType> $endTypes Expected result types at termination
     * @param int $height Stack height at block entry
     * @param bool $unreachable Whether current block is in unreachable state
     */
    public function __construct(
        public readonly int $opcode,
        public readonly array $startTypes,
        public readonly array $endTypes,
        public readonly int $height,
        public bool $unreachable = false,
    ) {
    }

    /**
     * @return list<ValueType> Types expected when branching to this label
     */
    public function labelTypes(): array
    {
        return $this->opcode === Opcode::LOOP ? $this->startTypes : $this->endTypes;
    }
}
