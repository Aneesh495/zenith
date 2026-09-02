<?php

declare(strict_types=1);

namespace Zenith\Runtime;

use RuntimeException;
use Zenith\Ast\ValueType;
use Zenith\Numeric\Int32;

/**
 * Representation of a typed runtime value in the WebAssembly stack machine.
 */
final class Value
{
    private function __construct(
        public readonly ValueType $type,
        public readonly mixed $raw,
    ) {
    }

    public static function i32(int $val): self
    {
        return new self(ValueType::I32, Int32::toSigned($val));
    }

    public static function i64(int $val): self
    {
        return new self(ValueType::I64, $val);
    }

    public static function f32(float $val): self
    {
        return new self(ValueType::F32, $val);
    }

    public static function f64(float $val): self
    {
        return new self(ValueType::F64, $val);
    }

    public static function funcRef(?int $funcIdx): self
    {
        return new self(ValueType::FuncRef, $funcIdx);
    }

    public static function externRef(mixed $ref): self
    {
        return new self(ValueType::ExternRef, $ref);
    }

    public function asI32(): int
    {
        if ($this->type !== ValueType::I32) {
            throw new RuntimeException("Expected i32, got {$this->type->name()}");
        }
        return (int)$this->raw;
    }

    public function asI64(): int
    {
        if ($this->type !== ValueType::I64) {
            throw new RuntimeException("Expected i64, got {$this->type->name()}");
        }
        return (int)$this->raw;
    }

    public function asF32(): float
    {
        if ($this->type !== ValueType::F32) {
            throw new RuntimeException("Expected f32, got {$this->type->name()}");
        }
        return (float)$this->raw;
    }

    public function asF64(): float
    {
        if ($this->type !== ValueType::F64) {
            throw new RuntimeException("Expected f64, got {$this->type->name()}");
        }
        return (float)$this->raw;
    }

    public function __toString(): string
    {
        return match ($this->type) {
            ValueType::I32 => sprintf('%d : i32', $this->raw),
            ValueType::I64 => sprintf('%d : i64', $this->raw),
            ValueType::F32 => sprintf('%g : f32', $this->raw),
            ValueType::F64 => sprintf('%g : f64', $this->raw),
            ValueType::FuncRef => sprintf('funcref(%s)', $this->raw !== null ? (string)$this->raw : 'null'),
            ValueType::ExternRef => 'externref',
            default => 'unknown',
        };
    }
}
