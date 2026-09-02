<?php

declare(strict_types=1);

namespace Zenith\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zenith\Ast\Export;
use Zenith\Ast\ExternalKind;
use Zenith\Ast\FuncType;
use Zenith\Ast\FunctionDef;
use Zenith\Ast\Instruction;
use Zenith\Ast\Limits;
use Zenith\Ast\Memory;
use Zenith\Ast\MemoryType;
use Zenith\Ast\Module;
use Zenith\Ast\ValueType;
use Zenith\Binary\Opcode;
use Zenith\Validator\ValidationError;
use Zenith\Validator\Validator;

final class ValidatorTest extends TestCase
{
    public function testValidModulePasses(): void
    {
        $mod = new Module();
        $mod->types[] = new FuncType([ValueType::I32, ValueType::I32], [ValueType::I32]);
        $mod->functions[] = new FunctionDef(0, [], [
            new Instruction(Opcode::LOCAL_GET, 0),
            new Instruction(Opcode::LOCAL_GET, 1),
            new Instruction(Opcode::I32_ADD),
            new Instruction(Opcode::END),
        ]);
        $mod->exports[] = new Export('add', ExternalKind::Function, 0);

        $this->expectNotToPerformAssertions();
        Validator::validate($mod);
    }

    public function testStackUnderflowFails(): void
    {
        $mod = new Module();
        $mod->types[] = new FuncType([], [ValueType::I32]);
        $mod->functions[] = new FunctionDef(0, [], [
            new Instruction(Opcode::I32_ADD), // underflow: expects two i32 operands
            new Instruction(Opcode::END),
        ]);

        $this->expectException(ValidationError::class);
        Validator::validate($mod);
    }

    public function testTypeMismatchFails(): void
    {
        $mod = new Module();
        $mod->types[] = new FuncType([], [ValueType::I32]);
        $mod->functions[] = new FunctionDef(0, [], [
            new Instruction(Opcode::F32_CONST, 3.14),
            new Instruction(Opcode::END), // returns f32 but function expects i32
        ]);

        $this->expectException(ValidationError::class);
        Validator::validate($mod);
    }

    public function testInvalidBranchDepthFails(): void
    {
        $mod = new Module();
        $mod->types[] = new FuncType([], []);
        $mod->functions[] = new FunctionDef(0, [], [
            new Instruction(Opcode::BR, 5), // target depth 5 does not exist
            new Instruction(Opcode::END),
        ]);

        $this->expectException(ValidationError::class);
        Validator::validate($mod);
    }

    public function testExcessiveMemoryLimitFails(): void
    {
        $mod = new Module();
        $mod->memories[] = new Memory(new MemoryType(new Limits(70000, null))); // > 65536 pages

        $this->expectException(ValidationError::class);
        Validator::validate($mod);
    }
}
