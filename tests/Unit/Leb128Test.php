<?php

declare(strict_types=1);

namespace Zenith\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zenith\Binary\BinaryReader;
use Zenith\Binary\Leb128;

final class Leb128Test extends TestCase
{
    public function testUnsignedRoundtrip(): void
    {
        $cases = [0, 1, 42, 127, 128, 255, 624485, 2147483647];
        foreach ($cases as $val) {
            $encoded = Leb128::encodeUint($val);
            $reader = new BinaryReader($encoded);
            $decoded = Leb128::decodeUint32($reader);
            $this->assertSame($val, $decoded, "Failed unsigned roundtrip for {$val}");
            $this->assertTrue($reader->isEof());
        }
    }

    public function testSignedRoundtrip(): void
    {
        $cases = [0, 1, -1, 63, -64, 64, -65, 127, -128, 624485, -624485, 2147483647, -2147483648];
        foreach ($cases as $val) {
            $encoded = Leb128::encodeInt($val);
            $reader = new BinaryReader($encoded);
            $decoded = Leb128::decodeInt32($reader);
            $this->assertSame($val, $decoded, "Failed signed roundtrip for {$val}");
            $this->assertTrue($reader->isEof());
        }
    }

    public function testKnownByteVectors(): void
    {
        // 624485 unsigned LEB128 is [0xE5, 0x8E, 0x26]
        $bytes = "\xE5\x8E\x26";
        $reader = new BinaryReader($bytes);
        $this->assertSame(624485, Leb128::decodeUint32($reader));
        $this->assertSame(3, $reader->getOffset());

        // -624485 signed LEB128 is [0x9B, 0xF1, 0x59]
        $sbytes = "\x9B\xF1\x59";
        $reader2 = new BinaryReader($sbytes);
        $this->assertSame(-624485, Leb128::decodeInt32($reader2));
        $this->assertSame(3, $reader2->getOffset());
    }
}
