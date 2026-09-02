<?php

declare(strict_types=1);

namespace Zenith\Binary;

use RuntimeException;
use Zenith\Numeric\Int32;
use Zenith\Numeric\Int64;

/**
 * Variable length LEB128 integer encoder and decoder.
 *
 * Implements unsigned (u32, u64) and signed (s32, s64) LEB128 representations
 * with strict byte limit validation according to the WebAssembly specification.
 */
final class Leb128
{
    public static function decodeUint32(BinaryReader $reader): int
    {
        $result = 0;
        $shift = 0;

        for ($i = 0; $i < 5; $i++) {
            $byte = $reader->readByte();
            $result |= ($byte & 0x7F) << $shift;

            if (($byte & 0x80) === 0) {
                if ($i === 4 && ($byte & 0x70) !== 0) {
                    throw new RuntimeException('integer representation too long');
                }
                return $result;
            }

            $shift += 7;
        }

        throw new RuntimeException('integer representation too long');
    }

    public static function decodeInt32(BinaryReader $reader): int
    {
        $result = 0;
        $shift = 0;
        $byte = 0;

        for ($i = 0; $i < 5; $i++) {
            $byte = $reader->readByte();
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;

            if (($byte & 0x80) === 0) {
                if ($shift < 32 && ($byte & 0x40) !== 0) {
                    $result |= -(1 << $shift);
                }
                return Int32::toSigned($result);
            }
        }

        throw new RuntimeException('integer representation too long');
    }

    public static function decodeUint64(BinaryReader $reader): int
    {
        $result = 0;
        $shift = 0;

        for ($i = 0; $i < 10; $i++) {
            $byte = $reader->readByte();
            $result |= ($byte & 0x7F) << $shift;

            if (($byte & 0x80) === 0) {
                return $result;
            }

            $shift += 7;
        }

        throw new RuntimeException('integer representation too long');
    }

    public static function decodeInt64(BinaryReader $reader): int
    {
        $result = 0;
        $shift = 0;
        $byte = 0;

        for ($i = 0; $i < 10; $i++) {
            $byte = $reader->readByte();
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;

            if (($byte & 0x80) === 0) {
                if ($shift < 64 && ($byte & 0x40) !== 0) {
                    $result |= -(1 << $shift);
                }
                return $result;
            }
        }

        throw new RuntimeException('integer representation too long');
    }

    public static function encodeUint(int $val): string
    {
        $out = '';
        do {
            $byte = $val & 0x7F;
            $val = Int64::shr_u($val, 7);
            if ($val !== 0) {
                $byte |= 0x80;
            }
            $out .= chr($byte);
        } while ($val !== 0);

        return $out;
    }

    public static function encodeInt(int $val): string
    {
        $out = '';
        $more = true;

        while ($more) {
            $byte = $val & 0x7F;
            $val >>= 7;
            $signBit = ($byte & 0x40) !== 0;

            if (($val === 0 && !$signBit) || ($val === -1 && $signBit)) {
                $more = false;
            } else {
                $byte |= 0x80;
            }

            $out .= chr($byte);
        }

        return $out;
    }
}
