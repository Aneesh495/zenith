<?php

declare(strict_types=1);

namespace Zenith\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zenith\Numeric\Int64;
use Zenith\Runtime\Trap;

final class Int64Test extends TestCase
{
    public function testAddition(): void
    {
        $this->assertSame(10, Int64::add(4, 6));
        $this->assertSame(0, Int64::add(-5, 5));
    }

    public function testSubtraction(): void
    {
        $this->assertSame(-2, Int64::sub(4, 6));
        $this->assertSame(10, Int64::sub(5, -5));
    }

    public function testMultiplication(): void
    {
        $this->assertSame(24, Int64::mul(4, 6));
        $this->assertSame(-24, Int64::mul(-4, 6));
    }

    public function testDivisionSigned(): void
    {
        $this->assertSame(5, Int64::div_s(15, 3));
        $this->assertSame(-5, Int64::div_s(-15, 3));
    }

    public function testDivisionByZeroTrap(): void
    {
        $this->expectException(Trap::class);
        $this->expectExceptionMessage('integer divide by zero');
        Int64::div_s(10, 0);
    }

    public function testDivisionOverflowTrap(): void
    {
        $this->expectException(Trap::class);
        $this->expectExceptionMessage('integer overflow');
        Int64::div_s(PHP_INT_MIN, -1);
    }

    public function testRemainderSigned(): void
    {
        $this->assertSame(2, Int64::rem_s(17, 5));
        $this->assertSame(-2, Int64::rem_s(-17, 5));
        $this->assertSame(0, Int64::rem_s(PHP_INT_MIN, -1));
    }

    public function testBitwiseOperations(): void
    {
        $this->assertSame(0x04, Int64::and(0x0C, 0x06));
        $this->assertSame(0x0E, Int64::or(0x0C, 0x06));
        $this->assertSame(0x0A, Int64::xor(0x0C, 0x06));
        $this->assertSame(32, Int64::shl(8, 2));
        $this->assertSame(-4, Int64::shr_s(-8, 1));
    }

    public function testRotations(): void
    {
        $this->assertSame(2, Int64::rotl(1, 1));
        $this->assertSame(PHP_INT_MIN, Int64::rotl(1, 63));
        $this->assertSame(1, Int64::rotr(PHP_INT_MIN, 63));
    }

    public function testBitCounting(): void
    {
        $this->assertSame(64, Int64::clz(0));
        $this->assertSame(0, Int64::clz(-1));
        $this->assertSame(63, Int64::clz(1));

        $this->assertSame(64, Int64::ctz(0));
        $this->assertSame(0, Int64::ctz(1));
        $this->assertSame(3, Int64::ctz(8));

        $this->assertSame(0, Int64::popcnt(0));
        $this->assertSame(1, Int64::popcnt(1));
        $this->assertSame(64, Int64::popcnt(-1));
    }

    public function testComparisons(): void
    {
        $this->assertSame(1, Int64::eqz(0));
        $this->assertSame(0, Int64::eqz(100));

        $this->assertSame(1, Int64::lt_s(-10, 10));
        $this->assertSame(0, Int64::lt_u(-10, 10));
        $this->assertSame(1, Int64::gt_u(-1, 0));
    }

    public function testSignExtensions(): void
    {
        $this->assertSame(-1, Int64::extend8_s(0xFF));
        $this->assertSame(-1, Int64::extend16_s(0xFFFF));
        $this->assertSame(-1, Int64::extend32_s(0xFFFFFFFF));
        $this->assertSame(2147483647, Int64::extend32_s(0x7FFFFFFF));
    }
}
