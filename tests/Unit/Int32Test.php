<?php

declare(strict_types=1);

namespace Zenith\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zenith\Numeric\Int32;
use Zenith\Runtime\Trap;

final class Int32Test extends TestCase
{
    public function testAdditionWrapping(): void
    {
        $this->assertSame(5, Int32::add(2, 3));
        $this->assertSame(-2147483648, Int32::add(2147483647, 1));
        $this->assertSame(0, Int32::add(-1, 1));
    }

    public function testSubtractionWrapping(): void
    {
        $this->assertSame(2, Int32::sub(5, 3));
        $this->assertSame(2147483647, Int32::sub(-2147483648, 1));
    }

    public function testMultiplicationWrapping(): void
    {
        $this->assertSame(6, Int32::mul(2, 3));
        $this->assertSame(-2, Int32::mul(2147483647, 2));
    }

    public function testDivisionSigned(): void
    {
        $this->assertSame(3, Int32::div_s(7, 2));
        $this->assertSame(-3, Int32::div_s(-7, 2));
        $this->assertSame(3, Int32::div_s(-7, -2));
    }

    public function testDivisionByZeroTrap(): void
    {
        $this->expectException(Trap::class);
        $this->expectExceptionMessage('integer divide by zero');
        Int32::div_s(10, 0);
    }

    public function testDivisionOverflowTrap(): void
    {
        $this->expectException(Trap::class);
        $this->expectExceptionMessage('integer overflow');
        Int32::div_s(-2147483648, -1);
    }

    public function testRemainderSigned(): void
    {
        $this->assertSame(1, Int32::rem_s(7, 2));
        $this->assertSame(-1, Int32::rem_s(-7, 2));
        $this->assertSame(0, Int32::rem_s(-2147483648, -1));
    }

    public function testBitwiseOperations(): void
    {
        $this->assertSame(0x04, Int32::and(0x0C, 0x06));
        $this->assertSame(0x0E, Int32::or(0x0C, 0x06));
        $this->assertSame(0x0A, Int32::xor(0x0C, 0x06));
        $this->assertSame(16, Int32::shl(4, 2));
        $this->assertSame(-2, Int32::shr_s(-4, 1));
        $this->assertSame(2147483646, Int32::shr_u(-4, 1));
    }

    public function testRotations(): void
    {
        $this->assertSame(2, Int32::rotl(1, 1));
        $this->assertSame(-2147483648, Int32::rotl(1, 31));
        $this->assertSame(1, Int32::rotr(-2147483648, 31));
    }

    public function testBitCounting(): void
    {
        $this->assertSame(32, Int32::clz(0));
        $this->assertSame(0, Int32::clz(-1));
        $this->assertSame(31, Int32::clz(1));

        $this->assertSame(32, Int32::ctz(0));
        $this->assertSame(0, Int32::ctz(1));
        $this->assertSame(2, Int32::ctz(4));

        $this->assertSame(0, Int32::popcnt(0));
        $this->assertSame(1, Int32::popcnt(1));
        $this->assertSame(32, Int32::popcnt(-1));
        $this->assertSame(16, Int32::popcnt(0x55555555));
    }

    public function testComparisons(): void
    {
        $this->assertSame(1, Int32::eqz(0));
        $this->assertSame(0, Int32::eqz(5));

        $this->assertSame(1, Int32::lt_s(-5, 5));
        $this->assertSame(0, Int32::lt_u(-5, 5)); // unsigned -5 is 0xFFFFFFFB > 5

        $this->assertSame(1, Int32::gt_u(-1, 0));
        $this->assertSame(0, Int32::gt_s(-1, 0));
    }

    public function testSignExtensions(): void
    {
        $this->assertSame(-1, Int32::extend8_s(0xFF));
        $this->assertSame(127, Int32::extend8_s(0x7F));
        $this->assertSame(-1, Int32::extend16_s(0xFFFF));
        $this->assertSame(32767, Int32::extend16_s(0x7FFF));
    }
}
