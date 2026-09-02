<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * WebAssembly element segment for table initialization.
 */
final class ElementSegment
{
    public const MODE_ACTIVE = 'active';
    public const MODE_PASSIVE = 'passive';
    public const MODE_DECLARATIVE = 'declarative';

    /**
     * @param string $mode One of active, passive, declarative
     * @param int $tableIndex Table index (for active mode)
     * @param list<Instruction> $offsetExpr Offset evaluation instructions (for active mode)
     * @param ValueType $elemType Element reference type (e.g. funcref)
     * @param list<int>|list<list<Instruction>> $elements Function indices or init expressions
     */
    public function __construct(
        public readonly string $mode,
        public readonly int $tableIndex = 0,
        public readonly array $offsetExpr = [],
        public readonly ValueType $elemType = ValueType::FuncRef,
        public readonly array $elements = [],
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

    public function isDeclarative(): bool
    {
        return $this->mode === self::MODE_DECLARATIVE;
    }
}
