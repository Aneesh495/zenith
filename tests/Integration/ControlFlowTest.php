<?php

declare(strict_types=1);

namespace Zenith\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Zenith\Runtime\Store;
use Zenith\Runtime\Vm;
use Zenith\Text\WatCompiler;
use Zenith\Validator\Validator;

final class ControlFlowTest extends TestCase
{
    private function instantiate(string $wat): \Zenith\Runtime\Instance
    {
        $module = WatCompiler::compile($wat);
        Validator::validate($module);
        $vm = new Vm();
        $store = new Store($vm);
        return $store->instantiate($module);
    }

    public function testBreakOutOfNestedBlocks(): void
    {
        $wat = <<<'WAT'
(module
  (func $test (export "test") (param $x i32) (result i32)
    (block $b0
      (block $b1
        (block $b2
          (br_if $b1 (i32.eq (local.get $x) (i32.const 1)))
          (br_if $b0 (i32.eq (local.get $x) (i32.const 2)))
          (return (i32.const 99))
        )
        (return (i32.const 88))
      )
    )
    (i32.const 77)
  )
)
WAT;
        $inst = $this->instantiate($wat);
        $this->assertSame(99, $inst->invoke('test', [0]));
        $this->assertSame(77, $inst->invoke('test', [1]));
        $this->assertSame(77, $inst->invoke('test', [2]));
    }

    public function testBrTable(): void
    {
        $wat = <<<'WAT'
(module
  (func $switch (export "switch") (param $idx i32) (result i32)
    (block $def
      (block $case2
        (block $case1
          (block $case0
            (br_table $case0 $case1 $case2 $def (local.get $idx))
          )
          (return (i32.const 100))
        )
        (return (i32.const 200))
      )
      (return (i32.const 300))
    )
    (i32.const 400)
  )
)
WAT;
        $inst = $this->instantiate($wat);
        $this->assertSame(100, $inst->invoke('switch', [0]));
        $this->assertSame(200, $inst->invoke('switch', [1]));
        $this->assertSame(300, $inst->invoke('switch', [2]));
        $this->assertSame(400, $inst->invoke('switch', [3]));
        $this->assertSame(400, $inst->invoke('switch', [99]));
    }

    public function testLoopWithBreakAndContinue(): void
    {
        $wat = <<<'WAT'
(module
  (func $sum_even (export "sum_even") (param $limit i32) (result i32)
    (local $i i32)
    (local $total i32)
    (local.set $i (i32.const 0))
    (local.set $total (i32.const 0))

    (block $exit
      (loop $cont
        (br_if $exit (i32.gt_s (local.get $i) (local.get $limit)))

        ;; If i is odd, increment and continue
        (if (i32.and (local.get $i) (i32.const 1))
          (then
            (local.set $i (i32.add (local.get $i) (i32.const 1)))
            (br $cont)
          )
        )

        ;; Add even number
        (local.set $total (i32.add (local.get $total) (local.get $i)))
        (local.set $i (i32.add (local.get $i) (i32.const 1)))
        (br $cont)
      )
    )
    local.get $total
  )
)
WAT;
        $inst = $this->instantiate($wat);
        // Even numbers up to 10: 0 + 2 + 4 + 6 + 8 + 10 = 30
        $this->assertSame(30, $inst->invoke('sum_even', [10]));
        // Even numbers up to 20: 30 + 12 + 14 + 16 + 18 + 20 = 110
        $this->assertSame(110, $inst->invoke('sum_even', [20]));
    }
}
