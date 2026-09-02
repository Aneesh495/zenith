<?php

declare(strict_types=1);

namespace Zenith\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Zenith\Analysis\Disassembler;
use Zenith\Binary\ModuleDecoder;
use Zenith\Binary\ModuleEncoder;
use Zenith\Text\WatCompiler;
use Zenith\Text\WatPrinter;
use Zenith\Validator\Validator;

final class WatToolchainTest extends TestCase
{
    public function testWatCompilationRoundtrip(): void
    {
        $wat = <<<'WAT'
(module
  (; Multi-line nested (; comment ;) here ;)
  (memory (export "memory") 1)
  (func $square (export "square") (param $x i32) (result i32)
    ;; Compute square using multiplication
    (i32.mul (local.get $x) (local.get $x))
  )
)
WAT;
        $mod = WatCompiler::compile($wat);
        Validator::validate($mod);

        $this->assertCount(1, $mod->functions);
        $this->assertCount(1, $mod->memories);
        $this->assertCount(2, $mod->exports);

        // Convert back to WAT text
        $printed = WatPrinter::print($mod);
        $this->assertStringContainsString('(module', $printed);
        $this->assertStringContainsString('i32.mul', $printed);

        // Re-compile printed WAT
        $mod2 = WatCompiler::compile($printed);
        Validator::validate($mod2);
        $this->assertCount(1, $mod2->functions);
    }

    public function testBinaryAssemblyAndDisassembly(): void
    {
        $wat = <<<'WAT'
(module
  (func $calc (export "calc") (param $a i32) (param $b i32) (result i32)
    (i32.add (local.get $a) (local.get $b))
  )
)
WAT;
        $mod = WatCompiler::compile($wat);
        $binary = ModuleEncoder::encode($mod);
        $this->assertStringStartsWith(ModuleDecoder::WASM_MAGIC, $binary);

        $decoded = ModuleDecoder::decode($binary);
        $disasm = Disassembler::disassembleFunction($decoded->functions[0], $decoded);

        $this->assertStringContainsString('local.get', $disasm);
        $this->assertStringContainsString('i32.add', $disasm);
    }
}
