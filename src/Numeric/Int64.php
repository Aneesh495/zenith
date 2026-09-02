<?php

declare(strict_types=1);

namespace Zenith\Numeric;

use Zenith\Runtime\Trap;

/**
 * WebAssembly 64-bit integer arithmetic and bitwise operations.
 *
 * Implements exact two's complement 64-bit arithmetic, bit manipulations,
 * and trap checking according to the WebAssembly 2.0 specification.
 */
final class Int64
{
    public const MIN_VALUE = -9223372036854775807 - 1;
    public const MAX_VALUE = 9223372036854775807;

    public static function toGmp(int $val): \GMP
    {
        if ($val >= 0) {
            return gmp_init($val);
        }
        return gmp_add(gmp_init('0x10000000000000000'), gmp_init($val));
    }

    public static function fromGmp(\GMP $g): int
    {
        $masked = gmp_and($g, gmp_init('0xFFFFFFFFFFFFFFFF'));
        if (gmp_cmp($masked, 0) === 0) {
            return 0;
        }
        $bin = str_pad(gmp_export($masked, 1, GMP_BIG_ENDIAN), 8, "\0", STR_PAD_LEFT);
        return unpack('J', $bin)[1];
    }

    public static function add(int $a, int $b): int
    {
        return self::fromGmp(gmp_add(self::toGmp($a), self::toGmp($b)));
    }

    public static function sub(int $a, int $b): int
    {
        return self::fromGmp(gmp_sub(self::toGmp($a), self::toGmp($b)));
    }

    public static function mul(int $a, int $b): int
    {
        return self::fromGmp(gmp_mul(self::toGmp($a), self::toGmp($b)));
    }

    public static function div_s(int $a, int $b): int
    {
        if ($b === 0) {
            throw Trap::divideByZero();
        }

        if ($a === self::MIN_VALUE && $b === -1) {
            throw Trap::integerOverflow();
        }

        return intdiv($a, $b);
    }

    public static function div_u(int $a, int $b): int
    {
        if ($b === 0) {
            throw Trap::divideByZero();
        }

        $ga = self::toGmp($a);
        $gb = self::toGmp($b);

        return self::fromGmp(gmp_div_q($ga, $gb));
    }

    public static function rem_s(int $a, int $b): int
    {
        if ($b === 0) {
            throw Trap::divideByZero();
        }

        if ($a === self::MIN_VALUE && $b === -1) {
            return 0;
        }

        return $a % $b;
    }

    public static function rem_u(int $a, int $b): int
    {
        if ($b === 0) {
            throw Trap::divideByZero();
        }

        $ga = self::toGmp($a);
        $gb = self::toGmp($b);

        return self::fromGmp(gmp_div_r($ga, $gb));
    }

    public static function and(int $a, int $b): int
    {
        return $a & $b;
    }

    public static function or(int $a, int $b): int
    {
        return $a | $b;
    }

    public static function xor(int $a, int $b): int
    {
        return $a ^ $b;
    }

    public static function shl(int $a, int $b): int
    {
        $shift = $b & 63;
        return $a << $shift;
    }

    public static function shr_s(int $a, int $b): int
    {
        $shift = $b & 63;
        return $a >> $shift;
    }

    public static function shr_u(int $a, int $b): int
    {
        $shift = $b & 63;
        if ($shift === 0) {
            return $a;
        }

        if ($a >= 0) {
            return $a >> $shift;
        }

        return (($a >> 1) & 0x7FFFFFFFFFFFFFFF) >> ($shift - 1);
    }

    public static function rotl(int $a, int $b): int
    {
        $shift = $b & 63;
        if ($shift === 0) {
            return $a;
        }

        return ($a << $shift) | self::shr_u($a, 64 - $shift);
    }

    public static function rotr(int $a, int $b): int
    {
        $shift = $b & 63;
        if ($shift === 0) {
            return $a;
        }

        return self::shr_u($a, $shift) | ($a << (64 - $shift));
    }

    public static function clz(int $a): int
    {
        if ($a === 0) {
            return 64;
        }

        $hi = self::shr_u($a, 32);
        if ($hi === 0) {
            return 32 + Int32::clz($a & 0xFFFFFFFF);
        }

        return Int32::clz($hi);
    }

    public static function ctz(int $a): int
    {
        if ($a === 0) {
            return 64;
        }

        $lo = $a & 0xFFFFFFFF;
        if ($lo === 0) {
            return 32 + Int32::ctz(self::shr_u($a, 32));
        }

        return Int32::ctz($lo);
    }

    public static function popcnt(int $a): int
    {
        $hi = self::shr_u($a, 32);
        $lo = $a & 0xFFFFFFFF;

        return Int32::popcnt($hi) + Int32::popcnt($lo);
    }

    public static function eqz(int $a): int
    {
        return $a === 0 ? 1 : 0;
    }

    public static function eq(int $a, int $b): int
    {
        return $a === $b ? 1 : 0;
    }

    public static function ne(int $a, int $b): int
    {
        return $a !== $b ? 1 : 0;
    }

    public static function lt_s(int $a, int $b): int
    {
        return $a < $b ? 1 : 0;
    }

    public static function lt_u(int $a, int $b): int
    {
        if (($a < 0) !== ($b < 0)) {
            return ($a >= 0) ? 1 : 0;
        }

        return ($a < $b) ? 1 : 0;
    }

    public static function gt_s(int $a, int $b): int
    {
        return $a > $b ? 1 : 0;
    }

    public static function gt_u(int $a, int $b): int
    {
        return self::lt_u($b, $a);
    }

    public static function le_s(int $a, int $b): int
    {
        return $a <= $b ? 1 : 0;
    }

    public static function le_u(int $a, int $b): int
    {
        return ($a === $b || self::lt_u($a, $b) === 1) ? 1 : 0;
    }

    public static function ge_s(int $a, int $b): int
    {
        return $a >= $b ? 1 : 0;
    }

    public static function ge_u(int $a, int $b): int
    {
        return ($a === $b || self::lt_u($b, $a) === 1) ? 1 : 0;
    }

    public static function extend8_s(int $val): int
    {
        $val &= 0xFF;
        return ($val & 0x80) !== 0 ? $val - 0x100 : $val;
    }

    public static function extend16_s(int $val): int
    {
        $val &= 0xFFFF;
        return ($val & 0x8000) !== 0 ? $val - 0x10000 : $val;
    }

    public static function extend32_s(int $val): int
    {
        $val &= 0xFFFFFFFF;
        return ($val & 0x80000000) !== 0 ? $val - 0x100000000 : $val;
    }
}
