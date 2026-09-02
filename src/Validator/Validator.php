<?php

declare(strict_types=1);

namespace Zenith\Validator;

use Zenith\Ast\BlockType;
use Zenith\Ast\ExternalKind;
use Zenith\Ast\FunctionDef;
use Zenith\Ast\FuncType;
use Zenith\Ast\GlobalVar;
use Zenith\Ast\Instruction;
use Zenith\Ast\Module;
use Zenith\Ast\ValueType;
use Zenith\Binary\Opcode;

/**
 * Formal WebAssembly 2.0 static bytecode validator and type checker.
 */
final class Validator
{
    public static function validate(Module $module): void
    {
        $v = new self();
        $v->validateModule($module);
    }

    public function validateModule(Module $module): void
    {
        // 1. Validate limits
        foreach ($module->memories as $idx => $mem) {
            $limits = $mem->type->limits;
            if ($limits->min > 65536) {
                throw new ValidationError("Memory {$idx} initial size exceeds maximum allowed (65536 pages)");
            }
            if ($limits->max !== null && ($limits->max < $limits->min || $limits->max > 65536)) {
                throw new ValidationError("Memory {$idx} invalid maximum page limit");
            }
        }

        foreach ($module->tables as $idx => $tbl) {
            $limits = $tbl->type->limits;
            if ($limits->max !== null && $limits->max < $limits->min) {
                throw new ValidationError("Table {$idx} maximum size less than initial size");
            }
        }

        // 2. Validate export unique names
        $exportNames = [];
        foreach ($module->exports as $exp) {
            if (isset($exportNames[$exp->name])) {
                throw new ValidationError("Duplicate export identifier: {$exp->name}");
            }
            $exportNames[$exp->name] = true;

            // Validate export target index exists
            match ($exp->kind) {
                ExternalKind::Function => (function () use ($module, $exp) {
                    if ($exp->index >= $module->getTotalFunctionCount()) {
                        throw new ValidationError("Exported function index {$exp->index} out of range");
                    }
                })(),
                ExternalKind::Memory => (function () use ($module, $exp) {
                    if ($exp->index >= $module->getTotalMemoryCount()) {
                        throw new ValidationError("Exported memory index {$exp->index} out of range");
                    }
                })(),
                ExternalKind::Table => (function () use ($module, $exp) {
                    if ($exp->index >= $module->getTotalTableCount()) {
                        throw new ValidationError("Exported table index {$exp->index} out of range");
                    }
                })(),
                ExternalKind::Global => (function () use ($module, $exp) {
                    if ($exp->index >= $module->getTotalGlobalCount()) {
                        throw new ValidationError("Exported global index {$exp->index} out of range");
                    }
                })(),
            };
        }

        // 3. Validate start function
        if ($module->start !== null) {
            if ($module->start >= $module->getTotalFunctionCount()) {
                throw new ValidationError("Start function index {$module->start} out of range");
            }
            $sig = $module->getFunctionType($module->start);
            if (!empty($sig->params) || !empty($sig->results)) {
                throw new ValidationError('Start function must have signature () -> ()');
            }
        }

        // 4. Validate globals
        foreach ($module->globals as $idx => $global) {
            $this->validateConstantExpression($global->initExpr, $global->type->valType, $module);
        }

        // 5. Validate element segments
        foreach ($module->elements as $idx => $elem) {
            if ($elem->isActive()) {
                if ($elem->tableIndex >= $module->getTotalTableCount()) {
                    throw new ValidationError("Element segment {$idx} references non-existent table {$elem->tableIndex}");
                }
                $this->validateConstantExpression($elem->offsetExpr, ValueType::I32, $module);
            }
        }

        // 6. Validate data segments
        foreach ($module->data as $idx => $data) {
            if ($data->isActive()) {
                if ($data->memoryIndex >= $module->getTotalMemoryCount()) {
                    throw new ValidationError("Data segment {$idx} references non-existent memory {$data->memoryIndex}");
                }
                $this->validateConstantExpression($data->offsetExpr, ValueType::I32, $module);
            }
        }

        // 7. Validate functions
        $importedCount = $module->getImportedFunctionCount();
        foreach ($module->functions as $i => $func) {
            $funcIdx = $importedCount + $i;
            $type = $module->types[$func->typeIndex] ?? throw new ValidationError("Missing type index {$func->typeIndex}");
            $this->validateFunction($func, $type, $module);
        }
    }

    /**
     * @param list<Instruction> $expr
     */
    private function validateConstantExpression(array $expr, ValueType $expectedType, Module $module): void
    {
        $hasResult = false;
        $actualType = null;

        foreach ($expr as $ins) {
            if ($ins->is(Opcode::END)) {
                break;
            }

            match ($ins->opcode) {
                Opcode::I32_CONST => (function () use (&$hasResult, &$actualType) {
                    $hasResult = true;
                    $actualType = ValueType::I32;
                })(),
                Opcode::I64_CONST => (function () use (&$hasResult, &$actualType) {
                    $hasResult = true;
                    $actualType = ValueType::I64;
                })(),
                Opcode::F32_CONST => (function () use (&$hasResult, &$actualType) {
                    $hasResult = true;
                    $actualType = ValueType::F32;
                })(),
                Opcode::F64_CONST => (function () use (&$hasResult, &$actualType) {
                    $hasResult = true;
                    $actualType = ValueType::F64;
                })(),
                Opcode::GLOBAL_GET => (function () use ($module, $ins, &$hasResult, &$actualType) {
                    $gIdx = (int)$ins->immediate;
                    if ($gIdx >= $module->getTotalGlobalCount()) {
                        throw new ValidationError("Unknown global {$gIdx} in constant expression");
                    }
                    $hasResult = true;
                    $actualType = ValueType::I32; // Default for global
                })(),
                Opcode::REF_NULL => (function () use (&$hasResult, &$actualType) {
                    $hasResult = true;
                    $actualType = ValueType::FuncRef;
                })(),
                Opcode::REF_FUNC => (function () use ($module, $ins, &$hasResult, &$actualType) {
                    $fIdx = (int)$ins->immediate;
                    if ($fIdx >= $module->getTotalFunctionCount()) {
                        throw new ValidationError("Unknown func {$fIdx} in constant ref.func");
                    }
                    $hasResult = true;
                    $actualType = ValueType::FuncRef;
                })(),
                default => throw new ValidationError("Instruction {$ins->getMnemonic()} is not allowed in constant expressions"),
            };
        }

        if (!$hasResult || $actualType !== $expectedType) {
            throw new ValidationError(sprintf(
                'Constant expression type mismatch: expected %s, got %s',
                $expectedType->name(),
                $actualType?->name() ?? 'void'
            ));
        }
    }

    private function validateFunction(FunctionDef $func, FuncType $type, Module $module): void
    {
        $allLocals = array_merge($type->params, $func->locals);
        $ctx = new ValidationContext($module, $allLocals, $type->results);

        // Root function frame
        $ctx->pushCtrl(Opcode::BLOCK, [], $type->results);

        foreach ($func->body as $ins) {
            $this->validateInstruction($ins, $ctx);
        }

        if ($ctx->hasControlFrames()) {
            $frame = $ctx->popCtrl();
            $ctx->pushVals($frame->endTypes);
        }
    }

    private function validateInstruction(Instruction $ins, ValidationContext $ctx): void
    {
        $op = $ins->opcode;

        switch ($op) {
            case Opcode::UNREACHABLE:
                $ctx->setUnreachable();
                break;

            case Opcode::NOP:
                break;

            case Opcode::BLOCK:
            case Opcode::LOOP:
                $bt = $ins->immediate instanceof BlockType ? $ins->immediate : BlockType::empty();
                [$startTypes, $endTypes] = $ctx->expandBlockType($bt);
                $ctx->popVals($startTypes);
                $ctx->pushCtrl($op, $startTypes, $endTypes);
                break;

            case Opcode::IF:
                $ctx->popVal(ValueType::I32); // condition
                $bt = $ins->immediate instanceof BlockType ? $ins->immediate : BlockType::empty();
                [$startTypes, $endTypes] = $ctx->expandBlockType($bt);
                $ctx->popVals($startTypes);
                $ctx->pushCtrl(Opcode::IF, $startTypes, $endTypes);
                break;

            case Opcode::ELSE:
                $frame = $ctx->popCtrl();
                if ($frame->opcode !== Opcode::IF) {
                    throw new ValidationError('else without matching if');
                }
                $ctx->pushCtrl(Opcode::ELSE, $frame->startTypes, $frame->endTypes);
                break;

            case Opcode::END:
                $frame = $ctx->popCtrl();
                $ctx->pushVals($frame->endTypes);
                break;

            case Opcode::BR:
                $depth = (int)$ins->immediate;
                $target = $ctx->getLabel($depth);
                $ctx->popVals($target->labelTypes());
                $ctx->setUnreachable();
                break;

            case Opcode::BR_IF:
                $depth = (int)$ins->immediate;
                $target = $ctx->getLabel($depth);
                $ctx->popVal(ValueType::I32);
                $ctx->popVals($target->labelTypes());
                $ctx->pushVals($target->labelTypes());
                break;

            case Opcode::BR_TABLE:
                $ctx->popVal(ValueType::I32); // table selector index
                $defaultDepth = (int)$ins->immediate['default'];
                $defaultTarget = $ctx->getLabel($defaultDepth);
                $expectedTypes = $defaultTarget->labelTypes();

                foreach ($ins->immediate['targets'] as $tDepth) {
                    $target = $ctx->getLabel((int)$tDepth);
                    if (count($target->labelTypes()) !== count($expectedTypes)) {
                        throw new ValidationError('br_table target arity mismatch');
                    }
                }

                $ctx->popVals($expectedTypes);
                $ctx->setUnreachable();
                break;

            case Opcode::RETURN:
                $ctx->popVals($ctx->returns);
                $ctx->setUnreachable();
                break;

            case Opcode::CALL:
                $funcIdx = (int)$ins->immediate;
                $sig = $ctx->module->getFunctionType($funcIdx);
                $ctx->popVals($sig->params);
                $ctx->pushVals($sig->results);
                break;

            case Opcode::CALL_INDIRECT:
                $typeIdx = (int)$ins->immediate['typeIndex'];
                $tableIdx = (int)$ins->immediate['tableIndex'];
                if ($tableIdx >= $ctx->module->getTotalTableCount()) {
                    throw new ValidationError("call_indirect references invalid table {$tableIdx}");
                }
                $sig = $ctx->module->types[$typeIdx] ?? throw new ValidationError("call_indirect invalid type {$typeIdx}");
                $ctx->popVal(ValueType::I32); // table dynamic index
                $ctx->popVals($sig->params);
                $ctx->pushVals($sig->results);
                break;

            case Opcode::DROP:
                $ctx->popVal();
                break;

            case Opcode::SELECT:
                $ctx->popVal(ValueType::I32); // condition
                $t2 = $ctx->popVal();
                $t1 = $ctx->popVal();
                if ($t1 !== null && $t2 !== null && $t1 !== $t2) {
                    throw new ValidationError(sprintf('select operand mismatch: %s vs %s', $t1->name(), $t2->name()));
                }
                $ctx->pushVal($t1 ?? $t2);
                break;

            case Opcode::LOCAL_GET:
                $lIdx = (int)$ins->immediate;
                if (!isset($ctx->locals[$lIdx])) {
                    throw new ValidationError("Unknown local index {$lIdx}");
                }
                $ctx->pushVal($ctx->locals[$lIdx]);
                break;

            case Opcode::LOCAL_SET:
                $lIdx = (int)$ins->immediate;
                if (!isset($ctx->locals[$lIdx])) {
                    throw new ValidationError("Unknown local index {$lIdx}");
                }
                $ctx->popVal($ctx->locals[$lIdx]);
                break;

            case Opcode::LOCAL_TEE:
                $lIdx = (int)$ins->immediate;
                if (!isset($ctx->locals[$lIdx])) {
                    throw new ValidationError("Unknown local index {$lIdx}");
                }
                $ctx->popVal($ctx->locals[$lIdx]);
                $ctx->pushVal($ctx->locals[$lIdx]);
                break;

            case Opcode::GLOBAL_GET:
                $gIdx = (int)$ins->immediate;
                if ($gIdx >= $ctx->module->getTotalGlobalCount()) {
                    throw new ValidationError("Unknown global index {$gIdx}");
                }
                $ctx->pushVal(ValueType::I32); // standard default
                break;

            case Opcode::GLOBAL_SET:
                $gIdx = (int)$ins->immediate;
                if ($gIdx >= $ctx->module->getTotalGlobalCount()) {
                    throw new ValidationError("Unknown global index {$gIdx}");
                }
                $ctx->popVal();
                break;

            case Opcode::I32_CONST:
                $ctx->pushVal(ValueType::I32);
                break;

            case Opcode::I64_CONST:
                $ctx->pushVal(ValueType::I64);
                break;

            case Opcode::F32_CONST:
                $ctx->pushVal(ValueType::F32);
                break;

            case Opcode::F64_CONST:
                $ctx->pushVal(ValueType::F64);
                break;

            // i32 unary
            case Opcode::I32_EQZ:
            case Opcode::I32_CLZ:
            case Opcode::I32_CTZ:
            case Opcode::I32_POPCNT:
                $ctx->popVal(ValueType::I32);
                $ctx->pushVal(ValueType::I32);
                break;

            // i32 binary
            case Opcode::I32_ADD:
            case Opcode::I32_SUB:
            case Opcode::I32_MUL:
            case Opcode::I32_DIV_S:
            case Opcode::I32_DIV_U:
            case Opcode::I32_REM_S:
            case Opcode::I32_REM_U:
            case Opcode::I32_AND:
            case Opcode::I32_OR:
            case Opcode::I32_XOR:
            case Opcode::I32_SHL:
            case Opcode::I32_SHR_S:
            case Opcode::I32_SHR_U:
            case Opcode::I32_ROTL:
            case Opcode::I32_ROTR:
            case Opcode::I32_EQ:
            case Opcode::I32_NE:
            case Opcode::I32_LT_S:
            case Opcode::I32_LT_U:
            case Opcode::I32_GT_S:
            case Opcode::I32_GT_U:
            case Opcode::I32_LE_S:
            case Opcode::I32_LE_U:
            case Opcode::I32_GE_S:
            case Opcode::I32_GE_U:
                $ctx->popVal(ValueType::I32);
                $ctx->popVal(ValueType::I32);
                $ctx->pushVal(ValueType::I32);
                break;

            // i64 unary
            case Opcode::I64_EQZ:
                $ctx->popVal(ValueType::I64);
                $ctx->pushVal(ValueType::I32);
                break;
            case Opcode::I64_CLZ:
            case Opcode::I64_CTZ:
            case Opcode::I64_POPCNT:
                $ctx->popVal(ValueType::I64);
                $ctx->pushVal(ValueType::I64);
                break;

            // i64 binary
            case Opcode::I64_ADD:
            case Opcode::I64_SUB:
            case Opcode::I64_MUL:
            case Opcode::I64_DIV_S:
            case Opcode::I64_DIV_U:
            case Opcode::I64_REM_S:
            case Opcode::I64_REM_U:
            case Opcode::I64_AND:
            case Opcode::I64_OR:
            case Opcode::I64_XOR:
            case Opcode::I64_SHL:
            case Opcode::I64_SHR_S:
            case Opcode::I64_SHR_U:
            case Opcode::I64_ROTL:
            case Opcode::I64_ROTR:
                $ctx->popVal(ValueType::I64);
                $ctx->popVal(ValueType::I64);
                $ctx->pushVal(ValueType::I64);
                break;

            case Opcode::I64_EQ:
            case Opcode::I64_NE:
            case Opcode::I64_LT_S:
            case Opcode::I64_LT_U:
            case Opcode::I64_GT_S:
            case Opcode::I64_GT_U:
            case Opcode::I64_LE_S:
            case Opcode::I64_LE_U:
            case Opcode::I64_GE_S:
            case Opcode::I64_GE_U:
                $ctx->popVal(ValueType::I64);
                $ctx->popVal(ValueType::I64);
                $ctx->pushVal(ValueType::I32);
                break;

            // f32 unary
            case Opcode::F32_ABS:
            case Opcode::F32_NEG:
            case Opcode::F32_CEIL:
            case Opcode::F32_FLOOR:
            case Opcode::F32_TRUNC:
            case Opcode::F32_NEAREST:
            case Opcode::F32_SQRT:
                $ctx->popVal(ValueType::F32);
                $ctx->pushVal(ValueType::F32);
                break;

            // f32 binary
            case Opcode::F32_ADD:
            case Opcode::F32_SUB:
            case Opcode::F32_MUL:
            case Opcode::F32_DIV:
            case Opcode::F32_MIN:
            case Opcode::F32_MAX:
            case Opcode::F32_COPYSIGN:
                $ctx->popVal(ValueType::F32);
                $ctx->popVal(ValueType::F32);
                $ctx->pushVal(ValueType::F32);
                break;

            case Opcode::F32_EQ:
            case Opcode::F32_NE:
            case Opcode::F32_LT:
            case Opcode::F32_GT:
            case Opcode::F32_LE:
            case Opcode::F32_GE:
                $ctx->popVal(ValueType::F32);
                $ctx->popVal(ValueType::F32);
                $ctx->pushVal(ValueType::I32);
                break;

            // f64 unary
            case Opcode::F64_ABS:
            case Opcode::F64_NEG:
            case Opcode::F64_CEIL:
            case Opcode::F64_FLOOR:
            case Opcode::F64_TRUNC:
            case Opcode::F64_NEAREST:
            case Opcode::F64_SQRT:
                $ctx->popVal(ValueType::F64);
                $ctx->pushVal(ValueType::F64);
                break;

            // f64 binary
            case Opcode::F64_ADD:
            case Opcode::F64_SUB:
            case Opcode::F64_MUL:
            case Opcode::F64_DIV:
            case Opcode::F64_MIN:
            case Opcode::F64_MAX:
            case Opcode::F64_COPYSIGN:
                $ctx->popVal(ValueType::F64);
                $ctx->popVal(ValueType::F64);
                $ctx->pushVal(ValueType::F64);
                break;

            case Opcode::F64_EQ:
            case Opcode::F64_NE:
            case Opcode::F64_LT:
            case Opcode::F64_GT:
            case Opcode::F64_LE:
            case Opcode::F64_GE:
                $ctx->popVal(ValueType::F64);
                $ctx->popVal(ValueType::F64);
                $ctx->pushVal(ValueType::I32);
                break;

            // Memory load
            case Opcode::I32_LOAD:
            case Opcode::I32_LOAD8_S:
            case Opcode::I32_LOAD8_U:
            case Opcode::I32_LOAD16_S:
            case Opcode::I32_LOAD16_U:
                $ctx->popVal(ValueType::I32);
                $ctx->pushVal(ValueType::I32);
                break;

            case Opcode::I64_LOAD:
            case Opcode::I64_LOAD8_S:
            case Opcode::I64_LOAD8_U:
            case Opcode::I64_LOAD16_S:
            case Opcode::I64_LOAD16_U:
            case Opcode::I64_LOAD32_S:
            case Opcode::I64_LOAD32_U:
                $ctx->popVal(ValueType::I32);
                $ctx->pushVal(ValueType::I64);
                break;

            case Opcode::F32_LOAD:
                $ctx->popVal(ValueType::I32);
                $ctx->pushVal(ValueType::F32);
                break;

            case Opcode::F64_LOAD:
                $ctx->popVal(ValueType::I32);
                $ctx->pushVal(ValueType::F64);
                break;

            // Memory store
            case Opcode::I32_STORE:
            case Opcode::I32_STORE8:
            case Opcode::I32_STORE16:
                $ctx->popVal(ValueType::I32); // value
                $ctx->popVal(ValueType::I32); // address
                break;

            case Opcode::I64_STORE:
            case Opcode::I64_STORE8:
            case Opcode::I64_STORE16:
            case Opcode::I64_STORE32:
                $ctx->popVal(ValueType::I64);
                $ctx->popVal(ValueType::I32);
                break;

            case Opcode::F32_STORE:
                $ctx->popVal(ValueType::F32);
                $ctx->popVal(ValueType::I32);
                break;

            case Opcode::F64_STORE:
                $ctx->popVal(ValueType::F64);
                $ctx->popVal(ValueType::I32);
                break;

            case Opcode::MEMORY_SIZE:
                $ctx->pushVal(ValueType::I32);
                break;

            case Opcode::MEMORY_GROW:
                $ctx->popVal(ValueType::I32);
                $ctx->pushVal(ValueType::I32);
                break;

            // Conversions
            case Opcode::I32_WRAP_I64:
                $ctx->popVal(ValueType::I64);
                $ctx->pushVal(ValueType::I32);
                break;

            case Opcode::I32_TRUNC_F32_S:
            case Opcode::I32_TRUNC_F32_U:
                $ctx->popVal(ValueType::F32);
                $ctx->pushVal(ValueType::I32);
                break;

            case Opcode::I32_TRUNC_F64_S:
            case Opcode::I32_TRUNC_F64_U:
                $ctx->popVal(ValueType::F64);
                $ctx->pushVal(ValueType::I32);
                break;

            case Opcode::I64_EXTEND_I32_S:
            case Opcode::I64_EXTEND_I32_U:
                $ctx->popVal(ValueType::I32);
                $ctx->pushVal(ValueType::I64);
                break;

            case Opcode::I64_TRUNC_F32_S:
            case Opcode::I64_TRUNC_F32_U:
                $ctx->popVal(ValueType::F32);
                $ctx->pushVal(ValueType::I64);
                break;

            case Opcode::I64_TRUNC_F64_S:
            case Opcode::I64_TRUNC_F64_U:
                $ctx->popVal(ValueType::F64);
                $ctx->pushVal(ValueType::I64);
                break;

            case Opcode::F32_CONVERT_I32_S:
            case Opcode::F32_CONVERT_I32_U:
                $ctx->popVal(ValueType::I32);
                $ctx->pushVal(ValueType::F32);
                break;

            case Opcode::F32_CONVERT_I64_S:
            case Opcode::F32_CONVERT_I64_U:
                $ctx->popVal(ValueType::I64);
                $ctx->pushVal(ValueType::F32);
                break;

            case Opcode::F32_DEMOTE_F64:
                $ctx->popVal(ValueType::F64);
                $ctx->pushVal(ValueType::F32);
                break;

            case Opcode::F64_CONVERT_I32_S:
            case Opcode::F64_CONVERT_I32_U:
                $ctx->popVal(ValueType::I32);
                $ctx->pushVal(ValueType::F64);
                break;

            case Opcode::F64_CONVERT_I64_S:
            case Opcode::F64_CONVERT_I64_U:
                $ctx->popVal(ValueType::I64);
                $ctx->pushVal(ValueType::F64);
                break;

            case Opcode::F64_PROMOTE_F32:
                $ctx->popVal(ValueType::F32);
                $ctx->pushVal(ValueType::F64);
                break;

            case Opcode::I32_REINTERPRET_F32:
                $ctx->popVal(ValueType::F32);
                $ctx->pushVal(ValueType::I32);
                break;

            case Opcode::I64_REINTERPRET_F64:
                $ctx->popVal(ValueType::F64);
                $ctx->pushVal(ValueType::I64);
                break;

            case Opcode::F32_REINTERPRET_I32:
                $ctx->popVal(ValueType::I32);
                $ctx->pushVal(ValueType::F32);
                break;

            case Opcode::F64_REINTERPRET_I64:
                $ctx->popVal(ValueType::I64);
                $ctx->pushVal(ValueType::F64);
                break;

            case Opcode::I32_EXTEND8_S:
            case Opcode::I32_EXTEND16_S:
                $ctx->popVal(ValueType::I32);
                $ctx->pushVal(ValueType::I32);
                break;

            case Opcode::I64_EXTEND8_S:
            case Opcode::I64_EXTEND16_S:
            case Opcode::I64_EXTEND32_S:
                $ctx->popVal(ValueType::I64);
                $ctx->pushVal(ValueType::I64);
                break;

            default:
                break;
        }
    }
}
