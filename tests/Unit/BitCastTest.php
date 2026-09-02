<?php

declare(strict_types=1);

namespace Zenith\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zenith\Numeric\BitCast;

final class BitCastTest extends TestCase
{
    public function testFloat32Reinterpret(): void
    {
        // 1.0f in IEEE 754 is 0x3F800000 (1065353216)
        $bits = BitCast::f32ToU32(1.0);
        $this->assertSame(0x3F800000, $bits);

        $float = BitCast::i32ToF32(0x3F800000);
        $this->assertSame(1.0, $float);

        // 0.0 and -0.0
        $this->assertSame(0, BitCast::f32ToU32(0.0));
        $this->assertSame(0x80000000, BitCast::f32ToU32(-0.0));
    }

    public function testFloat64Reinterpret(): void
    {
        // 1.0 in IEEE 754 double precision is 0x3FF0000000000000 (4607182418800017408)
        $bits = BitCast::f64ToI64(1.0);
        $this->assertSame(0x3FF0000000000000, $bits);

        $double = BitCast::i64ToF64(0x3FF0000000000000);
        $this->assertSame(1.0, $double);
    }
}
