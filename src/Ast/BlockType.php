<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * Structured control block type (empty, single value, or type index).
 */
final class BlockType
{
    private function __construct(
        public readonly bool $isEmpty,
        public readonly ?ValueType $valType = null,
        public readonly ?int $typeIndex = null,
    ) {
    }

    public static function empty(): self
    {
        return new self(true);
    }

    public static function fromValueType(ValueType $type): self
    {
        return new self(false, $type);
    }

    public static function fromTypeIndex(int $index): self
    {
        return new self(false, null, $index);
    }

    public function isValueType(): bool
    {
        return $this->valType !== null;
    }

    public function isTypeIndex(): bool
    {
        return $this->typeIndex !== null;
    }
}
