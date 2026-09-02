<?php

declare(strict_types=1);

namespace Zenith\Numeric;

use Zenith\Runtime\Trap;

/**
 * WebAssembly 32-bit integer arithmetic and bitwise operations.
 *
 * Implements exact two's complement 32-bit arithmetic, bit manipulations,
 * and trap checking according to the WebAssembly 2.0 specification.
 */
final class Int32
{
    public const MIN_VALUE = -2147483648;
    public const MAX_VALUE = 2147483647;
    public const MASK = 0xFFFFFFFF;
    public const SIGN_BIT = 0x80000000;

    /**
     * Normalize an integer to a signed 32-bit range [-2147483648, 2147483647].
     */
    public static function toSigned(int $val): int
    {
        $val &= self::MASK;
        return ($val & self::SIGN_BIT) !== 0 ? $val - 0x100000000 : $val;
    }

    /**
     * Normalize an integer to an unsigned 32-bit range [0, 4294967295].
     */
    public static function toUnsigned(int $val): int
    {
        return $val & self::MASK;
    }

    public static function add(int $a, int $b): int
    {
        return self::toSigned($a + $b);
    }

    public static function sub(int $a, int $b): int
    {
        return self::toSigned($a - $b);
    }

    public static function mul(int $a, int $b): int
    {
        return self::toSigned($a * $b);
    }

    public static function div_s(int $a, int $b): int
    {
        $sa = self::toSigned($a);
        $sb = self::toSigned($b);

        if ($sb === 0) {
            throw Trap::divideByZero();
        }

        if ($sa === self::MIN_VALUE && $sb === -1) {
            throw Trap::integerOverflow();
        }

        return intdiv($sa, $sb);
    }

    public static function div_u(int $a, int $b): int
    {
        $ua = self::toUnsigned($a);
        $ub = self::toUnsigned($b);

        if ($ub === 0) {
            throw Trap::divideByZero();
        }

        return self::toSigned(intdiv($ua, $ub));
    }

    public static function rem_s(int $a, int $b): int
    {
        $sa = self::toSigned($a);
        $sb = self::toSigned($b);

        if ($sb === 0) {
            throw Trap::divideByZero();
        }

        if ($sa === self::MIN_VALUE && $sb === -1) {
            return 0;
        }

        return $sa % $sb;
    }

    public static function rem_u(int $a, int $b): int
    {
        $ua = self::toUnsigned($a);
        $ub = self::toUnsigned($b);

        if ($ub === 0) {
            throw Trap::divideByZero();
        }

        return self::toSigned($ua % $ub);
    }

    public static function and(int $a, int $b): int
    {
        return self::toSigned($a & $b);
    }

    public static function or(int $a, int $b): int
    {
        return self::toSigned($a | $b);
    }

    public static function xor(int $a, int $b): int
    {
        return self::toSigned($a ^ $b);
    }

    public static function shl(int $a, int $b): int
    {
        $shift = $b & 31;
        return self::toSigned(($a & self::MASK) << $shift);
    }

    public static function shr_s(int $a, int $b): int
    {
        $shift = $b & 31;
        return self::toSigned(self::toSigned($a) >> $shift);
    }

    public static function shr_u(int $a, int $b): int
    {
        $shift = $b & 31;
        return self::toSigned(self::toUnsigned($a) >> $shift);
    }

    public static function rotl(int $a, int $b): int
    {
        $shift = $b & 31;
        if ($shift === 0) {
            return self::toSigned($a);
        }

        $ua = self::toUnsigned($a);
        return self::toSigned((($ua << $shift) | ($ua >> (32 - $shift))) & self::MASK);
    }

    public static function rotr(int $a, int $b): int
    {
        $shift = $b & 31;
        if ($shift === 0) {
            return self::toSigned($a);
        }

        $ua = self::toUnsigned($a);
        return self::toSigned((($ua >> $shift) | ($ua << (32 - $shift))) & self::MASK);
    }

    public static function clz(int $a): int
    {
        $ua = self::toUnsigned($a);
        if ($ua === 0) {
            return 32;
        }

        $count = 0;
        if (($ua & 0xFFFF0000) === 0) {
            $count += 16;
            $ua <<= 16;
        }
        if (($ua & 0xFF000000) === 0) {
            $count += 8;
            $ua <<= 8;
        }
        if (($ua & 0xF0000000) === 0) {
            $count += 4;
            $ua <<= 4;
        }
        if (($ua & 0xC0000000) === 0) {
            $count += 2;
            $ua <<= 2;
        }
        if (($ua & 0x80000000) === 0) {
            $count += 1;
        }

        return $count;
    }

    public static function ctz(int $a): int
    {
        $ua = self::toUnsigned($a);
        if ($ua === 0) {
            return 32;
        }

        $count = 0;
        if (($ua & 0x0000FFFF) === 0) {
            $count += 16;
            $ua >>= 16;
        }
        if (($ua & 0x000000FF) === 0) {
            $count += 8;
            $ua >>= 8;
        }
        if (($ua & 0x0000000F) === 0) {
            $count += 4;
            $ua >>= 4;
        }
        if (($ua & 0x00000003) === 0) {
            $count += 2;
            $ua >>= 2;
        }
        if (($ua & 0x00000001) === 0) {
            $count += 1;
        }

        return $count;
    }

    public static function popcnt(int $a): int
    {
        $ua = self::toUnsigned($a);
        $ua = $ua - (($ua >> 1) & 0x55555555);
        $ua = ($ua & 0x33333333) + (($ua >> 2) & 0x33333333);
        $ua = ($ua + ($ua >> 4)) & 0x0F0F0F0F;
        $ua = $ua + ($ua >> 8);
        $ua = $ua + ($ua >> 16);
        return $ua & 0x3F;
    }

    public static function eqz(int $a): int
    {
        return self::toSigned($a) === 0 ? 1 : 0;
    }

    public static function eq(int $a, int $b): int
    {
        return self::toSigned($a) === self::toSigned($b) ? 1 : 0;
    }

    public static function ne(int $a, int $b): int
    {
        return self::toSigned($a) !== self::toSigned($b) ? 1 : 0;
    }

    public static function lt_s(int $a, int $b): int
    {
        return self::toSigned($a) < self::toSigned($b) ? 1 : 0;
    }

    public static function lt_u(int $a, int $b): int
    {
        return self::toUnsigned($a) < self::toUnsigned($b) ? 1 : 0;
    }

    public static function gt_s(int $a, int $b): int
    {
        return self::toSigned($a) > self::toSigned($b) ? 1 : 0;
    }

    public static function gt_u(int $a, int $b): int
    {
        return self::toUnsigned($a) > self::toUnsigned($b) ? 1 : 0;
    }

    public static function le_s(int $a, int $b): int
    {
        return self::toSigned($a) <= self::toSigned($b) ? 1 : 0;
    }

    public static function le_u(int $a, int $b): int
    {
        return self::toUnsigned($a) <= self::toUnsigned($b) ? 1 : 0;
    }

    public static function ge_s(int $a, int $b): int
    {
        return self::toSigned($a) >= self::toSigned($b) ? 1 : 0;
    }

    public static function ge_u(int $a, int $b): int
    {
        return self::toUnsigned($a) >= self::toUnsigned($b) ? 1 : 0;
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
}
