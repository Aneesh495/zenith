<?php

declare(strict_types=1);

namespace Zenith\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zenith\Ast\Limits;
use Zenith\Ast\MemoryType;
use Zenith\Runtime\Memory;
use Zenith\Runtime\Trap;

final class MemoryTest extends TestCase
{
    public function testMemoryInitializationAndSizing(): void
    {
        $mem = new Memory(new MemoryType(new Limits(2, 4)));
        $this->assertSame(2, $mem->pages());
        $this->assertSame(131072, $mem->sizeBytes());
    }

    public function testReadWriteIntegers(): void
    {
        $mem = new Memory(new MemoryType(new Limits(1, 2)));

        $mem->writeI8(0, 0x42);
        $this->assertSame(0x42, $mem->readI8(0));
        $this->assertSame(0x42, $mem->readU8(0));

        $mem->writeI16(2, 0x1234);
        $this->assertSame(0x1234, $mem->readI16(2));

        $mem->writeI32(4, 0x12345678);
        $this->assertSame(0x12345678, $mem->readI32(4));

        $mem->writeI64(8, 0x1122334455667788);
        $this->assertSame(0x1122334455667788, $mem->readI64(8));
    }

    public function testReadWriteFloats(): void
    {
        $mem = new Memory(new MemoryType(new Limits(1, 2)));

        $mem->writeF32(0, 3.140000104904175);
        $this->assertEqualsWithDelta(3.14, $mem->readF32(0), 0.001);

        $mem->writeF64(8, 2.718281828459);
        $this->assertSame(2.718281828459, $mem->readF64(8));
    }

    public function testOutOfBoundsTrap(): void
    {
        $mem = new Memory(new MemoryType(new Limits(1, 2)));

        $this->expectException(Trap::class);
        $this->expectExceptionMessage('out of bounds');
        $mem->readI32(65534); // 4 bytes from 65534 exceeds 65536
    }

    public function testMemoryGrow(): void
    {
        $mem = new Memory(new MemoryType(new Limits(1, 3)));
        $this->assertSame(1, $mem->pages());

        $prev = $mem->grow(1);
        $this->assertSame(1, $prev);
        $this->assertSame(2, $mem->pages());

        $prev2 = $mem->grow(1);
        $this->assertSame(2, $prev2);
        $this->assertSame(3, $mem->pages());

        // Grow beyond maximum limit should fail and return -1
        $failed = $mem->grow(1);
        $this->assertSame(-1, $failed);
        $this->assertSame(3, $mem->pages());
    }

    public function testBulkMemoryCopyAndFill(): void
    {
        $mem = new Memory(new MemoryType(new Limits(1, 2)));
        $mem->fill(10, 0xAA, 5);

        for ($i = 10; $i < 15; $i++) {
            $this->assertSame(0xAA, $mem->readU8($i));
        }

        $mem->copy(20, 10, 5);
        for ($i = 20; $i < 25; $i++) {
            $this->assertSame(0xAA, $mem->readU8($i));
        }
    }
}
