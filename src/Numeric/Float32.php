<?php

declare(strict_types=1);

namespace Zenith\Numeric;

use Zenith\Runtime\Trap;

/**
 * WebAssembly 32-bit floating point arithmetic.
 *
 * Implements IEEE 754 single precision float arithmetic, bit exact operations,
 * rounding modes, and trap checking according to the WebAssembly 2.0 specification.
 */
final class Float32
{
    public static function normalize(float $val): float
    {
        return unpack('f', pack('f', $val))[1];
    }

    public static function add(float $a, float $b): float
    {
        return self::normalize($a + $b);
    }

    public static function sub(float $a, float $b): float
    {
        return self::normalize($a - $b);
    }

    public static function mul(float $a, float $b): float
    {
        return self::normalize($a * $b);
    }

    public static function div(float $a, float $b): float
    {
        return self::normalize($a / $b);
    }

    public static function sqrt(float $a): float
    {
        if (is_nan($a)) {
            return NAN;
        }

        if ($a < 0.0 && !BitCast::isNegativeZero32($a)) {
            return NAN;
        }

        return self::normalize(sqrt($a));
    }

    public static function min(float $a, float $b): float
    {
        if (is_nan($a) || is_nan($b)) {
            return NAN;
        }

        if ($a === 0.0 && $b === 0.0) {
            if (BitCast::isNegativeZero32($a) || BitCast::isNegativeZero32($b)) {
                return BitCast::i32ToF32(Int32::SIGN_BIT);
            }
            return 0.0;
        }

        return $a < $b ? $a : $b;
    }

    public static function max(float $a, float $b): float
    {
        if (is_nan($a) || is_nan($b)) {
            return NAN;
        }

        if ($a === 0.0 && $b === 0.0) {
            if (BitCast::isNegativeZero32($a) && BitCast::isNegativeZero32($b)) {
                return BitCast::i32ToF32(Int32::SIGN_BIT);
            }
            return 0.0;
        }

        return $a > $b ? $a : $b;
    }

    public static function copysign(float $a, float $b): float
    {
        $bitsA = BitCast::f32ToU32($a);
        $bitsB = BitCast::f32ToU32($b);

        $signB = $bitsB & 0x80000000;
        $magA = $bitsA & 0x7FFFFFFF;

        return BitCast::i32ToF32($signB | $magA);
    }

    public static function abs(float $a): float
    {
        $bits = BitCast::f32ToU32($a) & 0x7FFFFFFF;
        return BitCast::i32ToF32($bits);
    }

    public static function neg(float $a): float
    {
        $bits = BitCast::f32ToU32($a) ^ 0x80000000;
        return BitCast::i32ToF32($bits);
    }

    public static function ceil(float $a): float
    {
        return self::normalize(ceil($a));
    }

    public static function floor(float $a): float
    {
        return self::normalize(floor($a));
    }

    public static function trunc(float $a): float
    {
        if (is_nan($a) || is_infinite($a)) {
            return $a;
        }
        return self::normalize($a >= 0.0 ? floor($a) : ceil($a));
    }

    public static function nearest(float $a): float
    {
        if (is_nan($a) || is_infinite($a)) {
            return $a;
        }
        return self::normalize(round($a, 0, PHP_ROUND_HALF_EVEN));
    }

    public static function eq(float $a, float $b): int
    {
        if (is_nan($a) || is_nan($b)) {
            return 0;
        }
        return $a == $b ? 1 : 0;
    }

    public static function ne(float $a, float $b): int
    {
        if (is_nan($a) || is_nan($b)) {
            return 1;
        }
        return $a != $b ? 1 : 0;
    }

    public static function lt(float $a, float $b): int
    {
        if (is_nan($a) || is_nan($b)) {
            return 0;
        }
        return $a < $b ? 1 : 0;
    }

    public static function gt(float $a, float $b): int
    {
        if (is_nan($a) || is_nan($b)) {
            return 0;
        }
        return $a > $b ? 1 : 0;
    }

    public static function le(float $a, float $b): int
    {
        if (is_nan($a) || is_nan($b)) {
            return 0;
        }
        return $a <= $b ? 1 : 0;
    }

    public static function ge(float $a, float $b): int
    {
        if (is_nan($a) || is_nan($b)) {
            return 0;
        }
        return $a >= $b ? 1 : 0;
    }

    public static function trunc_to_i32_s(float $val): int
    {
        if (is_nan($val)) {
            throw Trap::invalidConversion();
        }
        if ($val >= 2147483648.0 || $val <= -2147483649.0) {
            throw Trap::integerOverflow();
        }
        return (int)$val;
    }

    public static function trunc_to_i32_u(float $val): int
    {
        if (is_nan($val)) {
            throw Trap::invalidConversion();
        }
        if ($val >= 4294967296.0 || $val <= -1.0) {
            throw Trap::integerOverflow();
        }
        return Int32::toSigned((int)$val);
    }

    public static function trunc_to_i64_s(float $val): int
    {
        if (is_nan($val)) {
            throw Trap::invalidConversion();
        }
        if ($val >= 9223372036854775808.0 || $val <= -9223372036854775809.0) {
            throw Trap::integerOverflow();
        }
        return (int)$val;
    }

    public static function trunc_to_i64_u(float $val): int
    {
        if (is_nan($val)) {
            throw Trap::invalidConversion();
        }
        if ($val >= 18446744073709551616.0 || $val <= -1.0) {
            throw Trap::integerOverflow();
        }
        $g = gmp_init(sprintf('%.0f', floor($val)));
        return Int64::fromGmp($g);
    }

    public static function trunc_sat_to_i32_s(float $val): int
    {
        if (is_nan($val)) {
            return 0;
        }
        if ($val <= (float)Int32::MIN_VALUE) {
            return Int32::MIN_VALUE;
        }
        if ($val >= (float)Int32::MAX_VALUE) {
            return Int32::MAX_VALUE;
        }
        return (int)$val;
    }

    public static function trunc_sat_to_i32_u(float $val): int
    {
        if (is_nan($val) || $val <= 0.0) {
            return 0;
        }
        if ($val >= 4294967295.0) {
            return -1;
        }
        return Int32::toSigned((int)$val);
    }

    public static function trunc_sat_to_i64_s(float $val): int
    {
        if (is_nan($val)) {
            return 0;
        }
        if ($val <= (float)Int64::MIN_VALUE) {
            return Int64::MIN_VALUE;
        }
        if ($val >= (float)Int64::MAX_VALUE) {
            return Int64::MAX_VALUE;
        }
        return (int)$val;
    }

    public static function trunc_sat_to_i64_u(float $val): int
    {
        if (is_nan($val) || $val <= 0.0) {
            return 0;
        }
        if ($val >= 18446744073709551615.0) {
            return -1;
        }
        $g = gmp_init(sprintf('%.0f', floor($val)));
        return Int64::fromGmp($g);
    }
}
