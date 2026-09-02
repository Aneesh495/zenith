<?php

declare(strict_types=1);

/**
 * Zenith WebAssembly Demonstration Suite.
 *
 * Runs through the showcase WebAssembly programs:
 * 1. Recursive and Iterative Fibonacci
 * 2. Sieve of Eratosthenes
 * 3. Matrix Multiplication
 * 4. In-Memory QuickSort
 * 5. SHA-256 Block Compression
 * 6. Mandelbrot Set via WASI Preview 1 fd_write
 */

require_once __DIR__ . '/../src/autoload.php';

use Zenith\Cli\ConsoleOutput;
use Zenith\Debugger\Terminal;
use Zenith\Runtime\Store;
use Zenith\Runtime\Vm;
use Zenith\Text\WatCompiler;
use Zenith\Validator\Validator;
use Zenith\Wasi\WasiContext;
use Zenith\Wasi\WasiSnapshotPreview1;

ConsoleOutput::banner();
ConsoleOutput::header('Zenith WebAssembly Demonstration Suite');

function runWatDemo(string $filename, string $title, callable $action): void
{
    echo Terminal::color("==> Demo: {$title}", Terminal::BOLD . Terminal::CYAN) . "\n";
    $path = __DIR__ . '/' . $filename;
    $wat = file_get_contents($path);
    if ($wat === false) {
        throw new RuntimeException("Could not read {$filename}");
    }

    $t0 = hrtime(true);
    $module = WatCompiler::compile($wat);
    Validator::validate($module);
    $compileTime = (hrtime(true) - $t0) / 1_000_000.0;

    echo sprintf("    Compiled & Validated in %.2f ms\n", $compileTime);

    $action($module);
    echo "\n";
}

// 1. Fibonacci
runWatDemo('fibonacci.wat', 'Fibonacci Computation', function ($module) {
    $vm = new Vm();
    $store = new Store($vm);
    $inst = $store->instantiate($module);

    $t0 = hrtime(true);
    $resRec = $inst->invoke('fib_recursive', [20]);
    $tRec = (hrtime(true) - $t0) / 1_000_000.0;

    $t1 = hrtime(true);
    $resIter = $inst->invoke('fib_iterative', [35]);
    $tIter = (hrtime(true) - $t1) / 1_000_000.0;

    echo sprintf("    fib_recursive(20) = %d (took %.2f ms)\n", $resRec, $tRec);
    echo sprintf("    fib_iterative(35) = %d (took %.2f ms)\n", $resIter, $tIter);
});

// 2. Prime Sieve
runWatDemo('prime_sieve.wat', 'Sieve of Eratosthenes (Linear Memory)', function ($module) {
    $vm = new Vm();
    $store = new Store($vm);
    $inst = $store->instantiate($module);

    $t0 = hrtime(true);
    $primes = $inst->invoke('sieve', [10000]);
    $tSieve = (hrtime(true) - $t0) / 1_000_000.0;

    echo sprintf("    Found %d primes <= 10,000 in %.2f ms\n", $primes, $tSieve);
    echo sprintf("    is_prime(7919) = %d (prime)\n", $inst->invoke('is_prime', [7919]));
    echo sprintf("    is_prime(7920) = %d (composite)\n", $inst->invoke('is_prime', [7920]));
});

// 3. Matrix Multiplication
runWatDemo('matrix_multiply.wat', 'Float64 Matrix Multiplication', function ($module) {
    $vm = new Vm();
    $store = new Store($vm);
    $inst = $store->instantiate($module);

    $t0 = hrtime(true);
    $sum = $inst->invoke('matmul', [24]);
    $tMat = (hrtime(true) - $t0) / 1_000_000.0;

    echo sprintf("    24x24 Matrix Multiplication Grand Sum = %g in %.2f ms\n", $sum, $tMat);
});

// 4. QuickSort
runWatDemo('quicksort.wat', 'In-Memory QuickSort (500 items)', function ($module) {
    $vm = new Vm();
    $store = new Store($vm);
    $inst = $store->instantiate($module);

    $inst->invoke('populate', [500, 42]);
    $beforeSorted = $inst->invoke('is_sorted', [500]);

    $t0 = hrtime(true);
    $inst->invoke('quicksort', [0, 499]);
    $tSort = (hrtime(true) - $t0) / 1_000_000.0;

    $afterSorted = $inst->invoke('is_sorted', [500]);

    echo sprintf("    Array sorted before: %s | after: %s\n", $beforeSorted ? 'true' : 'false', $afterSorted ? 'true' : 'false');
    echo sprintf("    QuickSort 500 elements took %.2f ms\n", $tSort);
    echo sprintf("    Smallest element: %d, Largest element: %d\n", $inst->invoke('get', [0]), $inst->invoke('get', [499]));
});

// 5. SHA-256
runWatDemo('sha256.wat', 'Pure WebAssembly SHA-256 Compression', function ($module) {
    $vm = new Vm();
    $store = new Store($vm);
    $inst = $store->instantiate($module);

    $t0 = hrtime(true);
    $digest = $inst->invoke('sha256_compress', [5000]);
    $tSha = (hrtime(true) - $t0) / 1_000_000.0;

    echo sprintf("    5,000 rounds completed in %.2f ms (digest word: 0x%08X)\n", $tSha, $digest & 0xFFFFFFFF);
});

// 6. WASI Mandelbrot
runWatDemo('mandelbrot.wat', 'Mandelbrot Fractal (WASI Preview 1 Terminal Output)', function ($module) {
    $vm = new Vm();
    $store = new Store($vm);
    WasiSnapshotPreview1::bind($store, new WasiContext());

    $inst = $store->instantiate($module);
    $inst->invoke('_start');
});

echo Terminal::color('All demonstrations completed successfully.', Terminal::BRIGHT_GREEN . Terminal::BOLD) . "\n";
