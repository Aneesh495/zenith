<?php

declare(strict_types=1);

namespace Zenith\Numeric;

/**
 * Low-level bitcast utilities between integer and floating-point representations.
 *
 * Implements exact bitwise reinterpretation matching Wasm reinterpret instructions.
 */
final class BitCast
{
    /**
     * Reinterpret 32-bit float bytes as a signed 32-bit integer.
     */
    public static function f32ToI32(float $val): int
    {
        return unpack('l', pack('f', $val))[1];
    }

    /**
     * Reinterpret 32-bit float bytes as an unsigned 32-bit integer.
     */
    public static function f32ToU32(float $val): int
    {
        return unpack('V', pack('f', $val))[1];
    }

    /**
     * Reinterpret 32-bit integer bytes as a 32-bit float.
     */
    public static function i32ToF32(int $val): float
    {
        return unpack('f', pack('l', $val))[1];
    }

    /**
     * Reinterpret 64-bit float bytes as a signed 64-bit integer.
     */
    public static function f64ToI64(float $val): int
    {
        return unpack('q', pack('d', $val))[1];
    }

    /**
     * Reinterpret 64-bit integer bytes as a 64-bit float.
     */
    public static function i64ToF64(int $val): float
    {
        return unpack('d', pack('q', $val))[1];
    }

    /**
     * Check if a 32-bit float is negative zero (-0.0).
     */
    public static function isNegativeZero32(float $val): bool
    {
        return $val === 0.0 && self::f32ToU32($val) === 0x80000000;
    }

    /**
     * Check if a 64-bit float is negative zero (-0.0).
     */
    public static function isNegativeZero64(float $val): bool
    {
        return $val === 0.0 && self::f64ToI64($val) === (int)-9223372036854775808;
    }
}
