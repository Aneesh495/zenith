<?php

declare(strict_types=1);

namespace Zenith\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zenith\Ast\BlockType;
use Zenith\Ast\Export;
use Zenith\Ast\ExternalKind;
use Zenith\Ast\FuncType;
use Zenith\Ast\FunctionDef;
use Zenith\Ast\Instruction;
use Zenith\Ast\Module;
use Zenith\Ast\ValueType;
use Zenith\Binary\ModuleDecoder;
use Zenith\Binary\ModuleEncoder;
use Zenith\Binary\Opcode;

final class BinaryCodecTest extends TestCase
{
    public function testEmptyModuleRoundtrip(): void
    {
        $bin = ModuleDecoder::WASM_MAGIC . pack('V', ModuleDecoder::WASM_VERSION);
        $mod = ModuleDecoder::decode($bin);
        $this->assertCount(0, $mod->types);
        $this->assertCount(0, $mod->functions);

        $reEncoded = ModuleEncoder::encode($mod);
        $this->assertSame($bin, $reEncoded);
    }

    public function testSimpleFunctionModuleRoundtrip(): void
    {
        $mod = new Module();
        $mod->types[] = new FuncType([ValueType::I32, ValueType::I32], [ValueType::I32]);

        $body = [
            new Instruction(Opcode::LOCAL_GET, 0),
            new Instruction(Opcode::LOCAL_GET, 1),
            new Instruction(Opcode::I32_ADD),
            new Instruction(Opcode::END),
        ];

        $mod->functions[] = new FunctionDef(0, [], $body, 'add');
        $mod->exports[] = new Export('add', ExternalKind::Function, 0);

        $encoded = ModuleEncoder::encode($mod);
        $this->assertStringStartsWith(ModuleDecoder::WASM_MAGIC, $encoded);

        $decoded = ModuleDecoder::decode($encoded);
        $this->assertCount(1, $decoded->types);
        $this->assertCount(1, $decoded->functions);
        $this->assertCount(1, $decoded->exports);
        $this->assertSame('add', $decoded->exports[0]->name);
        $this->assertCount(4, $decoded->functions[0]->body);
    }
}
