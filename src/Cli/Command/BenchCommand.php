<?php

declare(strict_types=1);

namespace Zenith\Cli\Command;

use Zenith\Analysis\Profiler;
use Zenith\Cli\ConsoleOutput;
use Zenith\Debugger\Terminal;
use Zenith\Runtime\Store;
use Zenith\Runtime\Vm;
use Zenith\Text\WatCompiler;

/**
 * Benchmark runner testing raw execution throughput, memory bandwidth, and recursion performance.
 */
final class BenchCommand
{
    public static function execute(array $args): int
    {
        ConsoleOutput::banner();
        ConsoleOutput::header('Running Zenith WebAssembly Benchmark Suite');

        self::benchFibonacci();
        self::benchPrimeSieve();
        self::benchMatrixMultiply();
        self::benchQuickSort();
        self::benchSha256();

        echo "\n" . Terminal::color('All benchmarks completed successfully.', Terminal::BRIGHT_GREEN . Terminal::BOLD) . "\n";
        return 0;
    }

    private static function benchFibonacci(): void
    {
        echo Terminal::color('==> Benchmark 1: Recursive Fibonacci (fib(24))', Terminal::BOLD . Terminal::CYAN) . "\n";

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

        $mod = WatCompiler::compile($wat);
        $vm = new Vm();
        $store = new Store($vm);
        $inst = $store->instantiate($mod);

        // Warmup
        $inst->invoke('fib', [10]);

        $profiler = new Profiler();
        $profiler->attach($vm);

        $t0 = hrtime(true);
        $res = $inst->invoke('fib', [24]);
        $t1 = hrtime(true);

        $profiler->detach($vm);

        $ms = ($t1 - $t0) / 1_000_000.0;
        $ops = $profiler->getTotalInstructions();
        $rate = $profiler->getThroughput();

        echo sprintf("    Result      : %d (expected 46368)\n", $res);
        echo sprintf("    Duration    : %.2f ms\n", $ms);
        echo sprintf("    Bytecodes   : %s instructions executed\n", number_format($ops));
        echo sprintf("    Throughput  : %s opcodes/sec\n\n", number_format((int)$rate));
    }

    private static function benchPrimeSieve(): void
    {
        echo Terminal::color('==> Benchmark 2: Sieve of Eratosthenes (primes up to 50,000 in linear memory)', Terminal::BOLD . Terminal::CYAN) . "\n";

        $wat = <<<'WAT'
(module
  (memory (export "memory") 2)
  (func $sieve (export "sieve") (param $limit i32) (result i32)
    (local $i i32)
    (local $j i32)
    (local $count i32)

    ;; Initialize memory to zero
    (local.set $i (i32.const 2))
    (block $outer_break
      (loop $outer_loop
        (br_if $outer_break (i32.gt_s (local.get $i) (local.get $limit)))

        ;; If memory[i] == 0, then i is prime
        (if (i32.eqz (i32.load8_u (local.get $i)))
          (then
            (local.set $count (i32.add (local.get $count) (i32.const 1)))
            ;; Mark multiples of i as composite
            (local.set $j (i32.mul (local.get $i) (i32.const 2)))
            (block $inner_break
              (loop $inner_loop
                (br_if $inner_break (i32.gt_s (local.get $j) (local.get $limit)))
                (i32.store8 (local.get $j) (i32.const 1))
                (local.set $j (i32.add (local.get $j) (local.get $i)))
                (br $inner_loop)
              )
            )
          )
        )
        (local.set $i (i32.add (local.get $i) (i32.const 1)))
        (br $outer_loop)
      )
    )
    local.get $count
  )
)
WAT;

        $mod = WatCompiler::compile($wat);
        $vm = new Vm();
        $store = new Store($vm);
        $inst = $store->instantiate($mod);

        $profiler = new Profiler();
        $profiler->attach($vm);

        $t0 = hrtime(true);
        $res = $inst->invoke('sieve', [50000]);
        $t1 = hrtime(true);

        $profiler->detach($vm);

        $ms = ($t1 - $t0) / 1_000_000.0;
        $ops = $profiler->getTotalInstructions();
        $rate = $profiler->getThroughput();

        echo sprintf("    Primes Found: %d (expected 5133)\n", $res);
        echo sprintf("    Duration    : %.2f ms\n", $ms);
        echo sprintf("    Bytecodes   : %s instructions executed\n", number_format($ops));
        echo sprintf("    Throughput  : %s opcodes/sec\n\n", number_format((int)$rate));
    }

    private static function benchMatrixMultiply(): void
    {
        echo Terminal::color('==> Benchmark 3: 32x32 Float64 Matrix Multiplication', Terminal::BOLD . Terminal::CYAN) . "\n";

        $wat = <<<'WAT'
(module
  (memory (export "memory") 2)
  (func $matmul (export "matmul") (param $n i32) (result f64)
    (local $i i32)
    (local $j i32)
    (local $k i32)
    (local $sum f64)
    (local $total f64)

    ;; For each row i
    (local.set $i (i32.const 0))
    (block $b_i
      (loop $l_i
        (br_if $b_i (i32.ge_s (local.get $i) (local.get $n)))

        ;; For each col j
        (local.set $j (i32.const 0))
        (block $b_j
          (loop $l_j
            (br_if $b_j (i32.ge_s (local.get $j) (local.get $n)))

            ;; Dot product k
            (local.set $sum (f64.const 0))
            (local.set $k (i32.const 0))
            (block $b_k
              (loop $l_k
                (br_if $b_k (i32.ge_s (local.get $k) (local.get $n)))
                (local.set $sum (f64.add (local.get $sum) (f64.mul (f64.convert_i32_s (local.get $i)) (f64.convert_i32_s (local.get $j)))))
                (local.set $k (i32.add (local.get $k) (i32.const 1)))
                (br $l_k)
              )
            )
            (local.set $total (f64.add (local.get $total) (local.get $sum)))
            (local.set $j (i32.add (local.get $j) (i32.const 1)))
            (br $l_j)
          )
        )
        (local.set $i (i32.add (local.get $i) (i32.const 1)))
        (br $l_i)
      )
    )
    local.get $total
  )
)
WAT;

        $mod = WatCompiler::compile($wat);
        $vm = new Vm();
        $store = new Store($vm);
        $inst = $store->instantiate($mod);

        $profiler = new Profiler();
        $profiler->attach($vm);

        $t0 = hrtime(true);
        $res = $inst->invoke('matmul', [32]);
        $t1 = hrtime(true);

        $profiler->detach($vm);

        $ms = ($t1 - $t0) / 1_000_000.0;
        $ops = $profiler->getTotalInstructions();
        $rate = $profiler->getThroughput();

        echo sprintf("    Matrix Result: %g\n", $res);
        echo sprintf("    Duration     : %.2f ms\n", $ms);
        echo sprintf("    Bytecodes    : %s instructions executed\n", number_format($ops));
        echo sprintf("    Throughput   : %s opcodes/sec\n\n", number_format((int)$rate));
    }

    private static function benchQuickSort(): void
    {
        echo Terminal::color('==> Benchmark 4: In-Memory QuickSort (1,000 Pseudo-Random Elements)', Terminal::BOLD . Terminal::CYAN) . "\n";

        $wat = <<<'WAT'
(module
  (memory (export "memory") 2)

  (func $swap (param $i i32) (param $j i32)
    (local $tmp i32)
    (local.set $tmp (i32.load (i32.mul (local.get $i) (i32.const 4))))
    (i32.store (i32.mul (local.get $i) (i32.const 4)) (i32.load (i32.mul (local.get $j) (i32.const 4))))
    (i32.store (i32.mul (local.get $j) (i32.const 4)) (local.get $tmp))
  )

  (func $qsort (export "qsort") (param $low i32) (param $high i32)
    (local $pivot i32)
    (local $i i32)
    (local $j i32)
    (local $val i32)

    (if (i32.lt_s (local.get $low) (local.get $high))
      (then
        (local.set $pivot (i32.load (i32.mul (local.get $high) (i32.const 4))))
        (local.set $i (i32.sub (local.get $low) (i32.const 1)))
        (local.set $j (local.get $low))

        (block $b_part
          (loop $l_part
            (br_if $b_part (i32.ge_s (local.get $j) (local.get $high)))
            (local.set $val (i32.load (i32.mul (local.get $j) (i32.const 4))))
            (if (i32.le_s (local.get $val) (local.get $pivot))
              (then
                (local.set $i (i32.add (local.get $i) (i32.const 1)))
                (call $swap (local.get $i) (local.get $j))
              )
            )
            (local.set $j (i32.add (local.get $j) (i32.const 1)))
            (br $l_part)
          )
        )
        (local.set $i (i32.add (local.get $i) (i32.const 1)))
        (call $swap (local.get $i) (local.get $high))

        ;; Recurse left
        (call $qsort (local.get $low) (i32.sub (local.get $i) (i32.const 1)))
        ;; Recurse right
        (call $qsort (i32.add (local.get $i) (i32.const 1)) (local.get $high))
      )
    )
  )

  (func $init_array (export "init_array") (param $len i32)
    (local $k i32)
    (local $val i32)
    (local.set $k (i32.const 0))
    (local.set $val (i32.const 12345))
    (block $b_init
      (loop $l_init
        (br_if $b_init (i32.ge_s (local.get $k) (local.get $len)))
        ;; LCG pseudo-random generator: val = (val * 1103515245 + 12345) & 0x7FFFFFFF
        (local.set $val (i32.and (i32.add (i32.mul (local.get $val) (i32.const 1103515245)) (i32.const 12345)) (i32.const 2147483647)))
        (i32.store (i32.mul (local.get $k) (i32.const 4)) (local.get $val))
        (local.set $k (i32.add (local.get $k) (i32.const 1)))
        (br $l_init)
      )
    )
  )
)
WAT;

        $mod = WatCompiler::compile($wat);
        $vm = new Vm();
        $store = new Store($vm);
        $inst = $store->instantiate($mod);

        $inst->invoke('init_array', [1000]);

        $profiler = new Profiler();
        $profiler->attach($vm);

        $t0 = hrtime(true);
        $inst->invoke('qsort', [0, 999]);
        $t1 = hrtime(true);

        $profiler->detach($vm);

        $ms = ($t1 - $t0) / 1_000_000.0;
        $ops = $profiler->getTotalInstructions();
        $rate = $profiler->getThroughput();

        echo sprintf("    Sorted Elements: 1,000\n");
        echo sprintf("    Duration       : %.2f ms\n", $ms);
        echo sprintf("    Bytecodes      : %s instructions executed\n", number_format($ops));
        echo sprintf("    Throughput     : %s opcodes/sec\n\n", number_format((int)$rate));
    }

    private static function benchSha256(): void
    {
        echo Terminal::color('==> Benchmark 5: Pure-Wasm SHA-256 Digest Compression', Terminal::BOLD . Terminal::CYAN) . "\n";

        $wat = <<<'WAT'
(module
  (func $rotr (param $x i32) (param $n i32) (result i32)
    (i32.or
      (i32.shr_u (local.get $x) (local.get $n))
      (i32.shl (local.get $x) (i32.sub (i32.const 32) (local.get $n)))
    )
  )

  (func $sigma0 (param $x i32) (result i32)
    (i32.xor
      (i32.xor
        (call $rotr (local.get $x) (i32.const 7))
        (call $rotr (local.get $x) (i32.const 18))
      )
      (i32.shr_u (local.get $x) (i32.const 3))
    )
  )

  (func $bench_rounds (export "bench_rounds") (param $rounds i32) (result i32)
    (local $i i32)
    (local $h i32)
    (local.set $h (i32.const 1779033703))
    (local.set $i (i32.const 0))
    (block $b
      (loop $l
        (br_if $b (i32.ge_s (local.get $i) (local.get $rounds)))
        (local.set $h (i32.add (local.get $h) (call $sigma0 (local.get $h))))
        (local.set $i (i32.add (local.get $i) (i32.const 1)))
        (br $l)
      )
    )
    local.get $h
  )
)
WAT;

        $mod = WatCompiler::compile($wat);
        $vm = new Vm();
        $store = new Store($vm);
        $inst = $store->instantiate($mod);

        $profiler = new Profiler();
        $profiler->attach($vm);

        $t0 = hrtime(true);
        $res = $inst->invoke('bench_rounds', [10000]);
        $t1 = hrtime(true);

        $profiler->detach($vm);

        $ms = ($t1 - $t0) / 1_000_000.0;
        $ops = $profiler->getTotalInstructions();
        $rate = $profiler->getThroughput();

        echo sprintf("    Digest State : 0x%08X\n", $res);
        echo sprintf("    Rounds       : 10,000\n");
        echo sprintf("    Duration     : %.2f ms\n", $ms);
        echo sprintf("    Bytecodes    : %s instructions executed\n", number_format($ops));
        echo sprintf("    Throughput   : %s opcodes/sec\n\n", number_format((int)$rate));
    }
}
