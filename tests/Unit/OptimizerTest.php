<?php

declare(strict_types=1);

namespace Zenith\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zenith\Analysis\Optimizer;
use Zenith\Ast\FunctionDef;
use Zenith\Ast\Instruction;
use Zenith\Binary\Opcode;

final class OptimizerTest extends TestCase
{
    public function testConstantFoldingAddition(): void
    {
        $body = [
            new Instruction(Opcode::I32_CONST, 15),
            new Instruction(Opcode::I32_CONST, 25),
            new Instruction(Opcode::I32_ADD),
            new Instruction(Opcode::END),
        ];

        $func = new FunctionDef(0, [], $body);
        $count = Optimizer::optimizeFunction($func);

        $this->assertGreaterThan(0, $count);
        $this->assertSame(Opcode::I32_CONST, $func->body[0]->opcode);
        $this->assertSame(40, $func->body[0]->immediate);
        $this->assertSame(Opcode::END, $func->body[1]->opcode);
    }

    public function testPeepholeLocalTee(): void
    {
        $body = [
            new Instruction(Opcode::I32_CONST, 100),
            new Instruction(Opcode::LOCAL_SET, 1),
            new Instruction(Opcode::LOCAL_GET, 1),
            new Instruction(Opcode::END),
        ];

        $func = new FunctionDef(0, [], $body);
        $count = Optimizer::optimizeFunction($func);

        $this->assertGreaterThan(0, $count);
        $this->assertSame(Opcode::I32_CONST, $func->body[0]->opcode);
        $this->assertSame(Opcode::LOCAL_TEE, $func->body[1]->opcode);
        $this->assertSame(1, $func->body[1]->immediate);
        $this->assertSame(Opcode::END, $func->body[2]->opcode);
    }

    public function testDeadCodeEliminationAfterUnreachable(): void
    {
        $body = [
            new Instruction(Opcode::UNREACHABLE),
            new Instruction(Opcode::I32_CONST, 123),
            new Instruction(Opcode::DROP),
            new Instruction(Opcode::END),
        ];

        $func = new FunctionDef(0, [], $body);
        $count = Optimizer::optimizeFunction($func);

        $this->assertGreaterThan(0, $count);
        $this->assertSame(Opcode::UNREACHABLE, $func->body[0]->opcode);
        $this->assertSame(Opcode::END, $func->body[1]->opcode);
    }
}
