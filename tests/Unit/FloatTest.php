<?php

declare(strict_types=1);

namespace Zenith\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zenith\Numeric\Float32;
use Zenith\Numeric\Float64;
use Zenith\Runtime\Trap;

final class FloatTest extends TestCase
{
    public function testFloat32BasicMath(): void
    {
        $this->assertEqualsWithDelta(5.5, Float32::add(2.25, 3.25), 0.0001);
        $this->assertEqualsWithDelta(-1.0, Float32::sub(2.25, 3.25), 0.0001);
        $this->assertEqualsWithDelta(7.5, Float32::mul(2.5, 3.0), 0.0001);
        $this->assertEqualsWithDelta(2.5, Float32::div(7.5, 3.0), 0.0001);
        $this->assertEqualsWithDelta(3.0, Float32::sqrt(9.0), 0.0001);
    }

    public function testFloat64BasicMath(): void
    {
        $this->assertSame(5.5, Float64::add(2.25, 3.25));
        $this->assertSame(-1.0, Float64::sub(2.25, 3.25));
        $this->assertSame(7.5, Float64::mul(2.5, 3.0));
        $this->assertSame(2.5, Float64::div(7.5, 3.0));
        $this->assertSame(3.0, Float64::sqrt(9.0));
    }

    public function testFloatRounding(): void
    {
        $this->assertSame(3.0, Float64::ceil(2.3));
        $this->assertSame(2.0, Float64::floor(2.8));
        $this->assertSame(2.0, Float64::trunc(2.8));
        $this->assertSame(-2.0, Float64::trunc(-2.8));
        $this->assertSame(2.0, Float64::nearest(2.3));
        $this->assertSame(3.0, Float64::nearest(2.7));
    }

    public function testFloatMinMaxNanSemantics(): void
    {
        $this->assertTrue(is_nan(Float64::min(NAN, 5.0)));
        $this->assertTrue(is_nan(Float64::min(5.0, NAN)));
        $this->assertTrue(is_nan(Float64::max(NAN, 5.0)));
        $this->assertTrue(is_nan(Float64::max(5.0, NAN)));

        $this->assertSame(2.0, Float64::min(2.0, 5.0));
        $this->assertSame(5.0, Float64::max(2.0, 5.0));
    }

    public function testFloatCopysign(): void
    {
        $this->assertSame(-5.0, Float64::copysign(5.0, -1.0));
        $this->assertSame(5.0, Float64::copysign(-5.0, 1.0));
    }

    public function testTruncationTraps(): void
    {
        $this->expectException(Trap::class);
        $this->expectExceptionMessage('invalid conversion to integer');
        Float64::trunc_to_i32_s(NAN);
    }

    public function testTruncationOverflowTraps(): void
    {
        $this->expectException(Trap::class);
        $this->expectExceptionMessage('integer overflow');
        Float64::trunc_to_i32_s(1e15);
    }
}
