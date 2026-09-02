<?php

declare(strict_types=1);

namespace Zenith\Ast;

use InvalidArgumentException;

/**
 * WebAssembly value types and reference types.
 */
enum ValueType: int
{
    case I32 = 0x7F;
    case I64 = 0x7E;
    case F32 = 0x7D;
    case F64 = 0x7C;
    case V128 = 0x7B;
    case FuncRef = 0x70;
    case ExternRef = 0x6F;

    public function name(): string
    {
        return match ($this) {
            self::I32 => 'i32',
            self::I64 => 'i64',
            self::F32 => 'f32',
            self::F64 => 'f64',
            self::V128 => 'v128',
            self::FuncRef => 'funcref',
            self::ExternRef => 'externref',
        };
    }

    public function isNum(): bool
    {
        return match ($this) {
            self::I32, self::I64, self::F32, self::F64 => true,
            default => false,
        };
    }

    public function isRef(): bool
    {
        return match ($this) {
            self::FuncRef, self::ExternRef => true,
            default => false,
        };
    }

    public static function fromName(string $name): self
    {
        return match (strtolower($name)) {
            'i32' => self::I32,
            'i64' => self::I64,
            'f32' => self::F32,
            'f64' => self::F64,
            'v128' => self::V128,
            'funcref' => self::FuncRef,
            'externref' => self::ExternRef,
            default => throw new InvalidArgumentException("Unknown value type: {$name}"),
        };
    }

    public static function tryFromByte(int $byte): ?self
    {
        return self::tryFrom($byte);
    }
}
