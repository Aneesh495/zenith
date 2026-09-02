<?php

declare(strict_types=1);

namespace Zenith\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Zenith\Runtime\Store;
use Zenith\Runtime\Vm;
use Zenith\Text\WatCompiler;
use Zenith\Validator\Validator;
use Zenith\Wasi\WasiContext;
use Zenith\Wasi\WasiExitException;
use Zenith\Wasi\WasiSnapshotPreview1;

final class WasiTest extends TestCase
{
    public function testWasiFdWrite(): void
    {
        $wat = <<<'WAT'
(module
  (import "wasi_snapshot_preview1" "fd_write"
    (func $fd_write (param i32 i32 i32 i32) (result i32)))

  (memory (export "memory") 1)
  (data (i32.const 16) "Testing WASI stdout\n")

  (func $main (export "_start")
    (i32.store (i32.const 0) (i32.const 16))
    (i32.store (i32.const 4) (i32.const 20))
    (drop
      (call $fd_write
        (i32.const 1)
        (i32.const 0)
        (i32.const 1)
        (i32.const 8)
      )
    )
  )
)
WAT;
        $module = WatCompiler::compile($wat);
        Validator::validate($module);

        $vm = new Vm();
        $store = new Store($vm);
        $wasiCtx = new WasiContext(captureStdio: true);
        WasiSnapshotPreview1::bind($store, $wasiCtx);

        $inst = $store->instantiate($module);
        $inst->invoke('_start');

        $this->assertSame("Testing WASI stdout\n", $wasiCtx->getStdoutBuffer());
    }

    public function testWasiProcExit(): void
    {
        $wat = <<<'WAT'
(module
  (import "wasi_snapshot_preview1" "proc_exit"
    (func $proc_exit (param i32)))

  (func (export "_start")
    (call $proc_exit (i32.const 42))
  )
)
WAT;
        $module = WatCompiler::compile($wat);
        Validator::validate($module);

        $vm = new Vm();
        $store = new Store($vm);
        $wasiCtx = new WasiContext();
        WasiSnapshotPreview1::bind($store, $wasiCtx);

        $inst = $store->instantiate($module);

        try {
            $inst->invoke('_start');
            $this->fail('Expected WasiExitException was not thrown');
        } catch (WasiExitException $e) {
            $this->assertSame(42, $e->exitCode);
        }
    }
}
