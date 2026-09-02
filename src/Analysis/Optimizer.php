<?php

declare(strict_types=1);

namespace Zenith\Analysis;

use Zenith\Ast\FunctionDef;
use Zenith\Ast\Instruction;
use Zenith\Ast\Module;
use Zenith\Binary\Opcode;
use Zenith\Numeric\Int32;
use Zenith\Numeric\Int64;

/**
 * Bytecode optimizer performing constant folding, algebraic simplification,
 * peephole optimization, and dead code elimination.
 */
final class Optimizer
{
    public static function optimizeModule(Module $module): int
    {
        $totalOptimizations = 0;
        foreach ($module->functions as $func) {
            $totalOptimizations += self::optimizeFunction($func);
        }
        return $totalOptimizations;
    }

    public static function optimizeFunction(FunctionDef $func): int
    {
        $optimizations = 0;
        $changed = true;

        while ($changed) {
            $changed = false;
            $passCount = 0;

            // Pass 1: Constant folding
            $newBody = self::foldConstants($func->body, $passCount);
            if ($passCount > 0) {
                $func->body = $newBody;
                $optimizations += $passCount;
                $changed = true;
            }

            // Pass 2: Local.set + local.get -> local.tee
            $passCount = 0;
            $newBody = self::peepholeTee($func->body, $passCount);
            if ($passCount > 0) {
                $func->body = $newBody;
                $optimizations += $passCount;
                $changed = true;
            }

            // Pass 3: Dead code elimination after unconditional control transfers
            $passCount = 0;
            $newBody = self::eliminateDeadCode($func->body, $passCount);
            if ($passCount > 0) {
                $func->body = $newBody;
                $optimizations += $passCount;
                $changed = true;
            }

            // Pass 4: Nop stripping
            $newBody = array_values(array_filter($func->body, static fn(Instruction $ins): bool => !$ins->is(Opcode::NOP)));
            if (count($newBody) !== count($func->body)) {
                $func->body = $newBody;
                $changed = true;
            }
        }

        return $optimizations;
    }

    /**
     * @param list<Instruction> $body
     * @return list<Instruction>
     */
    private static function foldConstants(array $body, int &$count): array
    {
        $out = [];
        $len = count($body);
        $i = 0;

        while ($i < $len) {
            // Fold i32: (i32.const a) (i32.const b) (op)
            if ($i + 2 < $len
                && $body[$i]->is(Opcode::I32_CONST)
                && $body[$i + 1]->is(Opcode::I32_CONST)
            ) {
                $a = (int)$body[$i]->immediate;
                $b = (int)$body[$i + 1]->immediate;
                $nextOp = $body[$i + 2]->opcode;

                $folded = match ($nextOp) {
                    Opcode::I32_ADD => Int32::add($a, $b),
                    Opcode::I32_SUB => Int32::sub($a, $b),
                    Opcode::I32_MUL => Int32::mul($a, $b),
                    Opcode::I32_AND => Int32::and($a, $b),
                    Opcode::I32_OR => Int32::or($a, $b),
                    Opcode::I32_XOR => Int32::xor($a, $b),
                    Opcode::I32_SHL => Int32::shl($a, $b),
                    Opcode::I32_EQ => Int32::eq($a, $b),
                    Opcode::I32_NE => Int32::ne($a, $b),
                    Opcode::I32_LT_S => Int32::lt_s($a, $b),
                    Opcode::I32_LT_U => Int32::lt_u($a, $b),
                    Opcode::I32_GT_S => Int32::gt_s($a, $b),
                    Opcode::I32_GT_U => Int32::gt_u($a, $b),
                    default => null,
                };

                if ($folded !== null) {
                    $out[] = new Instruction(Opcode::I32_CONST, $folded);
                    $i += 3;
                    $count++;
                    continue;
                }
            }

            // Algebraic identity: (i32.const 0) (i32.add) -> nop
            if ($i + 1 < $len
                && $body[$i]->is(Opcode::I32_CONST)
                && (int)$body[$i]->immediate === 0
                && $body[$i + 1]->is(Opcode::I32_ADD)
            ) {
                $i += 2;
                $count++;
                continue;
            }

            $out[] = $body[$i];
            $i++;
        }

        return $out;
    }

    /**
     * @param list<Instruction> $body
     * @return list<Instruction>
     */
    private static function peepholeTee(array $body, int &$count): array
    {
        $out = [];
        $len = count($body);
        $i = 0;

        while ($i < $len) {
            if ($i + 1 < $len
                && $body[$i]->is(Opcode::LOCAL_SET)
                && $body[$i + 1]->is(Opcode::LOCAL_GET)
                && $body[$i]->immediate === $body[$i + 1]->immediate
            ) {
                $out[] = new Instruction(Opcode::LOCAL_TEE, $body[$i]->immediate);
                $i += 2;
                $count++;
                continue;
            }

            $out[] = $body[$i];
            $i++;
        }

        return $out;
    }

    /**
     * @param list<Instruction> $body
     * @return list<Instruction>
     */
    private static function eliminateDeadCode(array $body, int &$count): array
    {
        $out = [];
        $len = count($body);
        $i = 0;

        while ($i < $len) {
            $ins = $body[$i];
            $out[] = $ins;
            $i++;

            if ($ins->is(Opcode::BR) || $ins->is(Opcode::RETURN) || $ins->is(Opcode::UNREACHABLE)) {
                // Skip subsequent instructions until matching control end or else
                $depth = 0;
                while ($i < $len) {
                    $next = $body[$i];
                    if ($next->is(Opcode::BLOCK) || $next->is(Opcode::LOOP) || $next->is(Opcode::IF)) {
                        $depth++;
                    } elseif ($next->is(Opcode::END)) {
                        if ($depth === 0) {
                            break;
                        }
                        $depth--;
                    } elseif ($next->is(Opcode::ELSE)) {
                        if ($depth === 0) {
                            break;
                        }
                    }
                    $i++;
                    $count++;
                }
            }
        }

        return $out;
    }
}
