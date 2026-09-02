<?php

declare(strict_types=1);

namespace Zenith\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zenith\Ast\Limits;
use Zenith\Ast\TableType;
use Zenith\Ast\ValueType;
use Zenith\Runtime\Table;
use Zenith\Runtime\Trap;

final class TableTest extends TestCase
{
    public function testTableInitialState(): void
    {
        $table = new Table(new TableType(ValueType::FuncRef, new Limits(5, 10)));
        $this->assertSame(5, $table->size());
        $this->assertNull($table->get(0));
        $this->assertNull($table->get(4));
    }

    public function testSetAndGet(): void
    {
        $table = new Table(new TableType(ValueType::FuncRef, new Limits(5, 10)));
        $table->set(2, 42);
        $this->assertSame(42, $table->get(2));
    }

    public function testOutOfBoundsTrap(): void
    {
        $table = new Table(new TableType(ValueType::FuncRef, new Limits(5, 10)));
        $this->expectException(Trap::class);
        $this->expectExceptionMessage('out of bounds table access');
        $table->get(5);
    }

    public function testTableGrow(): void
    {
        $table = new Table(new TableType(ValueType::FuncRef, new Limits(2, 4)));
        $prev = $table->grow(2, 99);
        $this->assertSame(2, $prev);
        $this->assertSame(4, $table->size());
        $this->assertSame(99, $table->get(2));
        $this->assertSame(99, $table->get(3));

        // Grow past maximum limit of 4 should return -1
        $failed = $table->grow(1);
        $this->assertSame(-1, $failed);
    }
}
