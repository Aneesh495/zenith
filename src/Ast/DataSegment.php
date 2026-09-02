<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * WebAssembly data segment for linear memory initialization.
 */
final class DataSegment
{
    public const MODE_ACTIVE = 'active';
    public const MODE_PASSIVE = 'passive';

    /**
     * @param string $mode Either active or passive
     * @param int $memoryIndex Linear memory index (for active mode)
     * @param list<Instruction> $offsetExpr Offset evaluation instructions (for active mode)
     * @param string $data Raw binary payload bytes
     */
    public function __construct(
        public readonly string $mode,
        public readonly int $memoryIndex = 0,
        public readonly array $offsetExpr = [],
        public readonly string $data = '',
    ) {
    }

    public function isActive(): bool
    {
        return $this->mode === self::MODE_ACTIVE;
    }

    public function isPassive(): bool
    {
        return $this->mode === self::MODE_PASSIVE;
    }
}
