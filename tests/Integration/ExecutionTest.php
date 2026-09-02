<?php

declare(strict_types=1);

namespace Zenith\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Zenith\Runtime\Store;
use Zenith\Runtime\Trap;
use Zenith\Runtime\Vm;
use Zenith\Text\WatCompiler;
use Zenith\Validator\Validator;

final class ExecutionTest extends TestCase
{
    private function compileAndInstantiate(string $wat): \Zenith\Runtime\Instance
    {
        $module = WatCompiler::compile($wat);
        Validator::validate($module);
        $vm = new Vm();
        $store = new Store($vm);
        return $store->instantiate($module);
    }

    public function testArithmeticAndLocals(): void
    {
        $wat = <<<'WAT'
(module
  (func $add (export "add") (param $a i32) (param $b i32) (result i32)
    (i32.add (local.get $a) (local.get $b))
  )
)
WAT;
        $inst = $this->compileAndInstantiate($wat);
        $this->assertSame(42, $inst->invoke('add', [12, 30]));
        $this->assertSame(-5, $inst->invoke('add', [10, -15]));
    }

    public function testRecursiveFunction(): void
    {
        $wat = <<<'WAT'
(module
  (func $fib (export "fib") (param $n i32) (result i32)
    (if (result i32) (i32.le_s (local.get $n) (i32.const 1))
      (then (local.get $n))
      (else
        (i32.add
          (call $fib (i32.sub (local.get $n) (i32.const 1)))
          (call $fib (i32.sub (local.get $n) (i32.const 2)))
        )
      )
    )
  )
)
WAT;
        $inst = $this->compileAndInstantiate($wat);
        $this->assertSame(0, $inst->invoke('fib', [0]));
        $this->assertSame(1, $inst->invoke('fib', [1]));
        $this->assertSame(1, $inst->invoke('fib', [2]));
        $this->assertSame(55, $inst->invoke('fib', [10]));
        $this->assertSame(6765, $inst->invoke('fib', [20]));
    }

    public function testMemoryLoadStore(): void
    {
        $wat = <<<'WAT'
(module
  (memory (export "memory") 1)
  (func $write_read (export "write_read") (param $addr i32) (param $val i32) (result i32)
    (i32.store (local.get $addr) (local.get $val))
    (i32.load (local.get $addr))
  )
)
WAT;
        $inst = $this->compileAndInstantiate($wat);
        $this->assertSame(12345678, $inst->invoke('write_read', [64, 12345678]));
    }

    public function testMemoryOutOfBoundsTrap(): void
    {
        $wat = <<<'WAT'
(module
  (memory 1)
  (func $oob (export "oob")
    (i32.load (i32.const 70000))
    (drop)
  )
)
WAT;
        $inst = $this->compileAndInstantiate($wat);
        $this->expectException(Trap::class);
        $this->expectExceptionMessage('out of bounds');
        $inst->invoke('oob');
    }

    public function testGlobals(): void
    {
        $wat = <<<'WAT'
(module
  (global $counter (mut i32) (i32.const 10))
  (func $inc (export "inc") (param $delta i32) (result i32)
    (global.set $counter (i32.add (global.get $counter) (local.get $delta)))
    (global.get $counter)
  )
)
WAT;
        $inst = $this->compileAndInstantiate($wat);
        $this->assertSame(15, $inst->invoke('inc', [5]));
        $this->assertSame(25, $inst->invoke('inc', [10]));
    }

    public function testCallIndirect(): void
    {
        $wat = <<<'WAT'
(module
  (type $binop (func (param i32 i32) (result i32)))
  (table 2 funcref)
  (elem (i32.const 0) $add $sub)

  (func $add (type $binop) (param $a i32) (param $b i32) (result i32)
    (i32.add (local.get $a) (local.get $b))
  )

  (func $sub (type $binop) (param $a i32) (param $b i32) (result i32)
    (i32.sub (local.get $a) (local.get $b))
  )

  (func $dispatch (export "dispatch") (param $op i32) (param $x i32) (param $y i32) (result i32)
    (call_indirect (type $binop) (local.get $x) (local.get $y) (local.get $op))
  )
)
WAT;
        $inst = $this->compileAndInstantiate($wat);
        $this->assertSame(50, $inst->invoke('dispatch', [0, 30, 20])); // add
        $this->assertSame(10, $inst->invoke('dispatch', [1, 30, 20])); // sub
    }
}
