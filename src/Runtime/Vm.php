<?php

declare(strict_types=1);

namespace Zenith\Runtime;

use Closure;
use RuntimeException;
use Zenith\Ast\BlockType;
use Zenith\Ast\FunctionDef;
use Zenith\Ast\FuncType;
use Zenith\Ast\Instruction;
use Zenith\Ast\ValueType;
use Zenith\Binary\Opcode;
use Zenith\Numeric\BitCast;
use Zenith\Numeric\Float32;
use Zenith\Numeric\Float64;
use Zenith\Numeric\Int32;
use Zenith\Numeric\Int64;

/**
 * Zenith WebAssembly 2.0 stack virtual machine execution engine.
 *
 * Implements full 32-bit and 64-bit integer arithmetic, IEEE 754 float math,
 * structured control flow unwinding, linear memory load/stores, table dispatch,
 * host function invocations, and deterministic trap semantics.
 */
final class Vm
{
    public const MAX_CALL_STACK_DEPTH = 1024;

    private Stack $stack;
    /** @var list<Frame> */
    private array $callStack = [];
    private bool $tracing = false;
    private ?Closure $traceHook = null;

    /** @var array<int, array{else: array<int, int>, end: array<int, int>}> */
    private array $jumpTableCache = [];

    public function __construct(
        private readonly ?Store $store = null,
    ) {
        $this->stack = new Stack();
    }

    public function getStack(): Stack
    {
        return $this->stack;
    }

    public function setTracing(bool $enabled, ?Closure $hook = null): void
    {
        $this->tracing = $enabled;
        $this->traceHook = $hook;
    }

    /**
     * @param list<Instruction> $expr
     */
    public function evaluateConstExpression(array $expr, Instance $instance): Value
    {
        foreach ($expr as $ins) {
            if ($ins->is(Opcode::END)) {
                break;
            }

            match ($ins->opcode) {
                Opcode::I32_CONST => $this->stack->push(Value::i32((int)$ins->immediate)),
                Opcode::I64_CONST => $this->stack->push(Value::i64((int)$ins->immediate)),
                Opcode::F32_CONST => $this->stack->push(Value::f32((float)$ins->immediate)),
                Opcode::F64_CONST => $this->stack->push(Value::f64((float)$ins->immediate)),
                Opcode::GLOBAL_GET => (function () use ($instance, $ins) {
                    $g = $instance->getGlobal((int)$ins->immediate) ?? throw new RuntimeException('Unknown global in const expr');
                    $this->stack->push($g->get());
                })(),
                Opcode::REF_NULL => $this->stack->push(Value::funcRef(null)),
                Opcode::REF_FUNC => $this->stack->push(Value::funcRef((int)$ins->immediate)),
                default => throw new RuntimeException("Invalid instruction in constant expression: {$ins->getMnemonic()}"),
            };
        }

        return $this->stack->pop();
    }

    /**
     * @param list<mixed> $rawArgs
     * @return mixed Single value, array of values, or null
     */
    public function callFunction(WasmFunction|HostFunction $func, array $rawArgs = [], ?Instance $caller = null): mixed
    {
        if ($func instanceof HostFunction) {
            $typedArgs = [];
            foreach ($func->type->params as $i => $pt) {
                $arg = $rawArgs[$i] ?? 0;
                $typedArgs[] = match ($pt) {
                    ValueType::I32 => Value::i32((int)$arg),
                    ValueType::I64 => Value::i64((int)$arg),
                    ValueType::F32 => Value::f32((float)$arg),
                    ValueType::F64 => Value::f64((float)$arg),
                    ValueType::FuncRef => Value::funcRef($arg !== null ? (int)$arg : null),
                    ValueType::ExternRef => Value::externRef($arg),
                    default => Value::i32(0),
                };
            }
            $results = $func->invoke($caller ?? $func->instance ?? throw new RuntimeException('No instance for host function'), $typedArgs);
            if (empty($results)) {
                return null;
            }
            return count($results) === 1 ? $results[0]->raw : array_map(static fn(Value $v) => $v->raw, $results);
        }

        if (count($this->callStack) >= self::MAX_CALL_STACK_DEPTH) {
            throw Trap::callStackExhausted(count($this->callStack));
        }

        // Prepare local variables: parameters followed by zero-initialized locals
        $locals = [];
        foreach ($func->type->params as $i => $pt) {
            $arg = $rawArgs[$i] ?? 0;
            $locals[] = match ($pt) {
                ValueType::I32 => Value::i32((int)$arg),
                ValueType::I64 => Value::i64((int)$arg),
                ValueType::F32 => Value::f32((float)$arg),
                ValueType::F64 => Value::f64((float)$arg),
                ValueType::FuncRef => Value::funcRef($arg !== null ? (int)$arg : null),
                ValueType::ExternRef => Value::externRef($arg),
                default => Value::i32(0),
            };
        }

        foreach ($func->def->locals as $lt) {
            $locals[] = match ($lt) {
                ValueType::I32 => Value::i32(0),
                ValueType::I64 => Value::i64(0),
                ValueType::F32 => Value::f32(0.0),
                ValueType::F64 => Value::f64(0.0),
                ValueType::FuncRef => Value::funcRef(null),
                ValueType::ExternRef => Value::externRef(null),
                default => Value::i32(0),
            };
        }

        $returnArity = count($func->type->results);
        $frame = new Frame($func->instance, $func->def, $locals, $returnArity);

        // Precompute jump table for structured control flow if not cached
        $jumps = $this->getJumpTable($func->def);

        $this->callStack[] = $frame;
        $stackHeightBefore = $this->stack->height();

        // Push root frame label: continuation at end of body
        $rootLabel = new Label(count($func->def->body), $returnArity, $stackHeightBefore, false);
        $frame->pushLabel($rootLabel);

        $instructions = $func->def->body;
        $insCount = count($instructions);

        while ($frame->ip < $insCount) {
            $curIp = $frame->ip;
            $ins = $instructions[$curIp];
            $frame->ip++;

            if ($this->tracing && $this->traceHook !== null) {
                ($this->traceHook)($frame, $ins, $this->stack);
            }

            $op = $ins->opcode;

            // Fast dispatch for common operations
            switch ($op) {
                case Opcode::NOP:
                    break;

                case Opcode::UNREACHABLE:
                    throw Trap::unreachable();

                case Opcode::BLOCK:
                    $bt = $ins->immediate instanceof BlockType ? $ins->immediate : BlockType::empty();
                    $arity = $bt->valType !== null ? 1 : ($bt->typeIndex !== null ? count($frame->instance->types[$bt->typeIndex]->results) : 0);
                    $endIp = $jumps['end'][$curIp] ?? $insCount;
                    $frame->pushLabel(new Label($endIp + 1, $arity, $this->stack->height(), false));
                    break;

                case Opcode::LOOP:
                    $bt = $ins->immediate instanceof BlockType ? $ins->immediate : BlockType::empty();
                    $arity = $bt->typeIndex !== null ? count($frame->instance->types[$bt->typeIndex]->params) : 0;
                    // For loop, branching jumps back to instruction immediately following LOOP
                    $frame->pushLabel(new Label($curIp + 1, $arity, $this->stack->height(), true));
                    break;

                case Opcode::IF:
                    $cond = $this->stack->popI32();
                    $bt = $ins->immediate instanceof BlockType ? $ins->immediate : BlockType::empty();
                    $arity = $bt->valType !== null ? 1 : ($bt->typeIndex !== null ? count($frame->instance->types[$bt->typeIndex]->results) : 0);
                    $endIp = $jumps['end'][$curIp] ?? $insCount;
                    $elseIp = $jumps['else'][$curIp] ?? null;

                    $frame->pushLabel(new Label($endIp + 1, $arity, $this->stack->height(), false));

                    if ($cond === 0) {
                        if ($elseIp !== null) {
                            $frame->ip = $elseIp + 1;
                        } else {
                            $frame->popLabel();
                            $frame->ip = $endIp + 1;
                        }
                    }
                    break;

                case Opcode::ELSE:
                    // Reached end of THEN block: pop IF label and jump past matching END
                    $endIp = $jumps['end'][$curIp] ?? $insCount;
                    $frame->popLabel();
                    $frame->ip = $endIp + 1;
                    break;

                case Opcode::END:
                    if (!empty($frame->labels)) {
                        $frame->popLabel();
                    }
                    break;

                case Opcode::BR:
                    $depth = (int)$ins->immediate;
                    $target = $frame->getLabel($depth);
                    $this->stack->unwind($target->stackHeight, $target->arity);
                    for ($k = 0; $k <= $depth; $k++) {
                        $frame->popLabel();
                    }
                    if ($target->isLoop) {
                        // Re-push loop header label
                        $frame->pushLabel($target);
                    }
                    $frame->ip = $target->instructionPointer;
                    break;

                case Opcode::BR_IF:
                    $cond = $this->stack->popI32();
                    if ($cond !== 0) {
                        $depth = (int)$ins->immediate;
                        $target = $frame->getLabel($depth);
                        $this->stack->unwind($target->stackHeight, $target->arity);
                        for ($k = 0; $k <= $depth; $k++) {
                            $frame->popLabel();
                        }
                        if ($target->isLoop) {
                            $frame->pushLabel($target);
                        }
                        $frame->ip = $target->instructionPointer;
                    }
                    break;

                case Opcode::BR_TABLE:
                    $idx = $this->stack->popI32();
                    $targets = $ins->immediate['targets'];
                    $targetDepth = isset($targets[$idx]) ? (int)$targets[$idx] : (int)$ins->immediate['default'];
                    $target = $frame->getLabel($targetDepth);
                    $this->stack->unwind($target->stackHeight, $target->arity);
                    for ($k = 0; $k <= $targetDepth; $k++) {
                        $frame->popLabel();
                    }
                    if ($target->isLoop) {
                        $frame->pushLabel($target);
                    }
                    $frame->ip = $target->instructionPointer;
                    break;

                case Opcode::RETURN:
                    $this->stack->unwind($stackHeightBefore, $returnArity);
                    $frame->ip = $insCount; // Terminate execution loop
                    break;

                case Opcode::CALL:
                    $funcIdx = (int)$ins->immediate;
                    $targetFunc = $frame->instance->getFunction($funcIdx);
                    $paramCount = count($targetFunc->type->params);
                    $args = [];
                    for ($p = 0; $p < $paramCount; $p++) {
                        $args[] = $this->stack->pop();
                    }
                    $args = array_reverse($args);

                    $rawCallArgs = array_map(static fn(Value $v) => $v->raw, $args);
                    $ret = $this->callFunction($targetFunc, $rawCallArgs, $frame->instance);

                    if (!empty($targetFunc->type->results)) {
                        if (count($targetFunc->type->results) === 1) {
                            $rType = $targetFunc->type->results[0];
                            $this->stack->push(match ($rType) {
                                ValueType::I32 => Value::i32((int)$ret),
                                ValueType::I64 => Value::i64((int)$ret),
                                ValueType::F32 => Value::f32((float)$ret),
                                ValueType::F64 => Value::f64((float)$ret),
                                default => Value::i32((int)$ret),
                            });
                        } elseif (is_array($ret)) {
                            foreach ($ret as $ri => $rv) {
                                $rType = $targetFunc->type->results[$ri];
                                $this->stack->push(match ($rType) {
                                    ValueType::I32 => Value::i32((int)$rv),
                                    ValueType::I64 => Value::i64((int)$rv),
                                    ValueType::F32 => Value::f32((float)$rv),
                                    ValueType::F64 => Value::f64((float)$rv),
                                    default => Value::i32((int)$rv),
                                });
                            }
                        }
                    }
                    break;

                case Opcode::CALL_INDIRECT:
                    $tblIdx = $this->stack->popI32();
                    $table = $frame->instance->getTable((int)$ins->immediate['tableIndex']);
                    if ($table === null) {
                        throw new RuntimeException('Table not found');
                    }

                    $resolvedFuncIdx = $table->get($tblIdx);
                    if ($resolvedFuncIdx === null) {
                        throw Trap::uninitializedElement($tblIdx);
                    }

                    $targetFunc = $frame->instance->getFunction($resolvedFuncIdx);
                    $expectedType = $frame->instance->types[(int)$ins->immediate['typeIndex']];

                    if (!$targetFunc->type->equals($expectedType)) {
                        throw Trap::typeMismatch((string)$expectedType, (string)$targetFunc->type);
                    }

                    $paramCount = count($targetFunc->type->params);
                    $args = [];
                    for ($p = 0; $p < $paramCount; $p++) {
                        $args[] = $this->stack->pop();
                    }
                    $args = array_reverse($args);

                    $rawCallArgs = array_map(static fn(Value $v) => $v->raw, $args);
                    $ret = $this->callFunction($targetFunc, $rawCallArgs, $frame->instance);

                    if (!empty($targetFunc->type->results)) {
                        if (count($targetFunc->type->results) === 1) {
                            $rType = $targetFunc->type->results[0];
                            $this->stack->push(match ($rType) {
                                ValueType::I32 => Value::i32((int)$ret),
                                ValueType::I64 => Value::i64((int)$ret),
                                ValueType::F32 => Value::f32((float)$ret),
                                ValueType::F64 => Value::f64((float)$ret),
                                default => Value::i32((int)$ret),
                            });
                        }
                    }
                    break;

                case Opcode::DROP:
                    $this->stack->pop();
                    break;

                case Opcode::SELECT:
                    $cond = $this->stack->popI32();
                    $val2 = $this->stack->pop();
                    $val1 = $this->stack->pop();
                    $this->stack->push($cond !== 0 ? $val1 : $val2);
                    break;

                case Opcode::LOCAL_GET:
                    $this->stack->push($frame->locals[(int)$ins->immediate]);
                    break;

                case Opcode::LOCAL_SET:
                    $frame->locals[(int)$ins->immediate] = $this->stack->pop();
                    break;

                case Opcode::LOCAL_TEE:
                    $v = $this->stack->peek();
                    $frame->locals[(int)$ins->immediate] = $v;
                    break;

                case Opcode::GLOBAL_GET:
                    $g = $frame->instance->getGlobal((int)$ins->immediate);
                    if ($g === null) {
                        throw new RuntimeException("Global {$ins->immediate} not found");
                    }
                    $this->stack->push($g->get());
                    break;

                case Opcode::GLOBAL_SET:
                    $g = $frame->instance->getGlobal((int)$ins->immediate);
                    if ($g === null) {
                        throw new RuntimeException("Global {$ins->immediate} not found");
                    }
                    $g->set($this->stack->pop());
                    break;

                case Opcode::TABLE_GET:
                    $t = $frame->instance->getTable(0);
                    $i = $this->stack->popI32();
                    $this->stack->push(Value::funcRef($t->get($i)));
                    break;

                case Opcode::TABLE_SET:
                    $t = $frame->instance->getTable(0);
                    $val = $this->stack->pop()->raw;
                    $i = $this->stack->popI32();
                    $t->set($i, $val);
                    break;

                // Memory load operations
                case Opcode::I32_LOAD:
                    $mem = $frame->instance->getMemory(0);
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $this->stack->push(Value::i32($mem->readI32($addr)));
                    break;

                case Opcode::I64_LOAD:
                    $mem = $frame->instance->getMemory(0);
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $this->stack->push(Value::i64($mem->readI64($addr)));
                    break;

                case Opcode::F32_LOAD:
                    $mem = $frame->instance->getMemory(0);
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $this->stack->push(Value::f32($mem->readF32($addr)));
                    break;

                case Opcode::F64_LOAD:
                    $mem = $frame->instance->getMemory(0);
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $this->stack->push(Value::f64($mem->readF64($addr)));
                    break;

                case Opcode::I32_LOAD8_S:
                    $mem = $frame->instance->getMemory(0);
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $this->stack->push(Value::i32($mem->readI8($addr)));
                    break;

                case Opcode::I32_LOAD8_U:
                    $mem = $frame->instance->getMemory(0);
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $this->stack->push(Value::i32($mem->readU8($addr)));
                    break;

                case Opcode::I32_LOAD16_S:
                    $mem = $frame->instance->getMemory(0);
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $this->stack->push(Value::i32($mem->readI16($addr)));
                    break;

                case Opcode::I32_LOAD16_U:
                    $mem = $frame->instance->getMemory(0);
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $this->stack->push(Value::i32($mem->readU16($addr)));
                    break;

                case Opcode::I64_LOAD8_S:
                    $mem = $frame->instance->getMemory(0);
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $this->stack->push(Value::i64($mem->readI8($addr)));
                    break;

                case Opcode::I64_LOAD8_U:
                    $mem = $frame->instance->getMemory(0);
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $this->stack->push(Value::i64($mem->readU8($addr)));
                    break;

                case Opcode::I64_LOAD16_S:
                    $mem = $frame->instance->getMemory(0);
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $this->stack->push(Value::i64($mem->readI16($addr)));
                    break;

                case Opcode::I64_LOAD16_U:
                    $mem = $frame->instance->getMemory(0);
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $this->stack->push(Value::i64($mem->readU16($addr)));
                    break;

                case Opcode::I64_LOAD32_S:
                    $mem = $frame->instance->getMemory(0);
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $this->stack->push(Value::i64($mem->readI32($addr)));
                    break;

                case Opcode::I64_LOAD32_U:
                    $mem = $frame->instance->getMemory(0);
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $this->stack->push(Value::i64($mem->readU32($addr)));
                    break;

                // Memory store operations
                case Opcode::I32_STORE:
                    $mem = $frame->instance->getMemory(0);
                    $val = $this->stack->popI32();
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $mem->writeI32($addr, $val);
                    break;

                case Opcode::I64_STORE:
                    $mem = $frame->instance->getMemory(0);
                    $val = $this->stack->popI64();
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $mem->writeI64($addr, $val);
                    break;

                case Opcode::F32_STORE:
                    $mem = $frame->instance->getMemory(0);
                    $val = $this->stack->popF32();
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $mem->writeF32($addr, $val);
                    break;

                case Opcode::F64_STORE:
                    $mem = $frame->instance->getMemory(0);
                    $val = $this->stack->popF64();
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $mem->writeF64($addr, $val);
                    break;

                case Opcode::I32_STORE8:
                case Opcode::I64_STORE8:
                    $mem = $frame->instance->getMemory(0);
                    $val = $op === Opcode::I32_STORE8 ? $this->stack->popI32() : $this->stack->popI64();
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $mem->writeI8($addr, $val);
                    break;

                case Opcode::I32_STORE16:
                case Opcode::I64_STORE16:
                    $mem = $frame->instance->getMemory(0);
                    $val = $op === Opcode::I32_STORE16 ? $this->stack->popI32() : $this->stack->popI64();
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $mem->writeI16($addr, $val);
                    break;

                case Opcode::I64_STORE32:
                    $mem = $frame->instance->getMemory(0);
                    $val = $this->stack->popI64();
                    $addr = $this->stack->popI32() + (int)($ins->immediate['offset'] ?? 0);
                    $mem->writeI32($addr, $val & 0xFFFFFFFF);
                    break;

                case Opcode::MEMORY_SIZE:
                    $mem = $frame->instance->getMemory(0);
                    $this->stack->push(Value::i32($mem->size()));
                    break;

                case Opcode::MEMORY_GROW:
                    $mem = $frame->instance->getMemory(0);
                    $delta = $this->stack->popI32();
                    $this->stack->push(Value::i32($mem->grow($delta)));
                    break;

                case Opcode::MEMORY_COPY:
                    $mem = $frame->instance->getMemory(0);
                    $count = $this->stack->popI32();
                    $src = $this->stack->popI32();
                    $dst = $this->stack->popI32();
                    $mem->copy($dst, $src, $count);
                    break;

                case Opcode::MEMORY_FILL:
                    $mem = $frame->instance->getMemory(0);
                    $count = $this->stack->popI32();
                    $val = $this->stack->popI32();
                    $dst = $this->stack->popI32();
                    $mem->fill($dst, $val, $count);
                    break;

                // Constants
                case Opcode::I32_CONST:
                    $this->stack->push(Value::i32((int)$ins->immediate));
                    break;

                case Opcode::I64_CONST:
                    $this->stack->push(Value::i64((int)$ins->immediate));
                    break;

                case Opcode::F32_CONST:
                    $this->stack->push(Value::f32((float)$ins->immediate));
                    break;

                case Opcode::F64_CONST:
                    $this->stack->push(Value::f64((float)$ins->immediate));
                    break;

                // i32 arithmetic
                case Opcode::I32_ADD:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::add($a, $b)));
                    break;

                case Opcode::I32_SUB:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::sub($a, $b)));
                    break;

                case Opcode::I32_MUL:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::mul($a, $b)));
                    break;

                case Opcode::I32_DIV_S:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::div_s($a, $b)));
                    break;

                case Opcode::I32_DIV_U:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::div_u($a, $b)));
                    break;

                case Opcode::I32_REM_S:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::rem_s($a, $b)));
                    break;

                case Opcode::I32_REM_U:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::rem_u($a, $b)));
                    break;

                case Opcode::I32_AND:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::and($a, $b)));
                    break;

                case Opcode::I32_OR:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::or($a, $b)));
                    break;

                case Opcode::I32_XOR:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::xor($a, $b)));
                    break;

                case Opcode::I32_SHL:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::shl($a, $b)));
                    break;

                case Opcode::I32_SHR_S:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::shr_s($a, $b)));
                    break;

                case Opcode::I32_SHR_U:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::shr_u($a, $b)));
                    break;

                case Opcode::I32_ROTL:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::rotl($a, $b)));
                    break;

                case Opcode::I32_ROTR:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::rotr($a, $b)));
                    break;

                case Opcode::I32_CLZ:
                    $this->stack->push(Value::i32(Int32::clz($this->stack->popI32())));
                    break;

                case Opcode::I32_CTZ:
                    $this->stack->push(Value::i32(Int32::ctz($this->stack->popI32())));
                    break;

                case Opcode::I32_POPCNT:
                    $this->stack->push(Value::i32(Int32::popcnt($this->stack->popI32())));
                    break;

                case Opcode::I32_EQZ:
                    $this->stack->push(Value::i32(Int32::eqz($this->stack->popI32())));
                    break;

                case Opcode::I32_EQ:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::eq($a, $b)));
                    break;

                case Opcode::I32_NE:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::ne($a, $b)));
                    break;

                case Opcode::I32_LT_S:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::lt_s($a, $b)));
                    break;

                case Opcode::I32_LT_U:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::lt_u($a, $b)));
                    break;

                case Opcode::I32_GT_S:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::gt_s($a, $b)));
                    break;

                case Opcode::I32_GT_U:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::gt_u($a, $b)));
                    break;

                case Opcode::I32_LE_S:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::le_s($a, $b)));
                    break;

                case Opcode::I32_LE_U:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::le_u($a, $b)));
                    break;

                case Opcode::I32_GE_S:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::ge_s($a, $b)));
                    break;

                case Opcode::I32_GE_U:
                    $b = $this->stack->popI32();
                    $a = $this->stack->popI32();
                    $this->stack->push(Value::i32(Int32::ge_u($a, $b)));
                    break;

                // i64 arithmetic
                case Opcode::I64_ADD:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i64(Int64::add($a, $b)));
                    break;

                case Opcode::I64_SUB:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i64(Int64::sub($a, $b)));
                    break;

                case Opcode::I64_MUL:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i64(Int64::mul($a, $b)));
                    break;

                case Opcode::I64_DIV_S:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i64(Int64::div_s($a, $b)));
                    break;

                case Opcode::I64_DIV_U:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i64(Int64::div_u($a, $b)));
                    break;

                case Opcode::I64_REM_S:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i64(Int64::rem_s($a, $b)));
                    break;

                case Opcode::I64_REM_U:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i64(Int64::rem_u($a, $b)));
                    break;

                case Opcode::I64_AND:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i64(Int64::and($a, $b)));
                    break;

                case Opcode::I64_OR:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i64(Int64::or($a, $b)));
                    break;

                case Opcode::I64_XOR:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i64(Int64::xor($a, $b)));
                    break;

                case Opcode::I64_SHL:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i64(Int64::shl($a, $b)));
                    break;

                case Opcode::I64_SHR_S:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i64(Int64::shr_s($a, $b)));
                    break;

                case Opcode::I64_SHR_U:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i64(Int64::shr_u($a, $b)));
                    break;

                case Opcode::I64_ROTL:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i64(Int64::rotl($a, $b)));
                    break;

                case Opcode::I64_ROTR:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i64(Int64::rotr($a, $b)));
                    break;

                case Opcode::I64_CLZ:
                    $this->stack->push(Value::i64(Int64::clz($this->stack->popI64())));
                    break;

                case Opcode::I64_CTZ:
                    $this->stack->push(Value::i64(Int64::ctz($this->stack->popI64())));
                    break;

                case Opcode::I64_POPCNT:
                    $this->stack->push(Value::i64(Int64::popcnt($this->stack->popI64())));
                    break;

                case Opcode::I64_EQZ:
                    $this->stack->push(Value::i32(Int64::eqz($this->stack->popI64())));
                    break;

                case Opcode::I64_EQ:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i32(Int64::eq($a, $b)));
                    break;

                case Opcode::I64_NE:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i32(Int64::ne($a, $b)));
                    break;

                case Opcode::I64_LT_S:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i32(Int64::lt_s($a, $b)));
                    break;

                case Opcode::I64_LT_U:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i32(Int64::lt_u($a, $b)));
                    break;

                case Opcode::I64_GT_S:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i32(Int64::gt_s($a, $b)));
                    break;

                case Opcode::I64_GT_U:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i32(Int64::gt_u($a, $b)));
                    break;

                case Opcode::I64_LE_S:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i32(Int64::le_s($a, $b)));
                    break;

                case Opcode::I64_LE_U:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i32(Int64::le_u($a, $b)));
                    break;

                case Opcode::I64_GE_S:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i32(Int64::ge_s($a, $b)));
                    break;

                case Opcode::I64_GE_U:
                    $b = $this->stack->popI64();
                    $a = $this->stack->popI64();
                    $this->stack->push(Value::i32(Int64::ge_u($a, $b)));
                    break;

                // f32 operations
                case Opcode::F32_ADD:
                    $b = $this->stack->popF32();
                    $a = $this->stack->popF32();
                    $this->stack->push(Value::f32(Float32::add($a, $b)));
                    break;

                case Opcode::F32_SUB:
                    $b = $this->stack->popF32();
                    $a = $this->stack->popF32();
                    $this->stack->push(Value::f32(Float32::sub($a, $b)));
                    break;

                case Opcode::F32_MUL:
                    $b = $this->stack->popF32();
                    $a = $this->stack->popF32();
                    $this->stack->push(Value::f32(Float32::mul($a, $b)));
                    break;

                case Opcode::F32_DIV:
                    $b = $this->stack->popF32();
                    $a = $this->stack->popF32();
                    $this->stack->push(Value::f32(Float32::div($a, $b)));
                    break;

                case Opcode::F32_SQRT:
                    $this->stack->push(Value::f32(Float32::sqrt($this->stack->popF32())));
                    break;

                case Opcode::F32_MIN:
                    $b = $this->stack->popF32();
                    $a = $this->stack->popF32();
                    $this->stack->push(Value::f32(Float32::min($a, $b)));
                    break;

                case Opcode::F32_MAX:
                    $b = $this->stack->popF32();
                    $a = $this->stack->popF32();
                    $this->stack->push(Value::f32(Float32::max($a, $b)));
                    break;

                case Opcode::F32_COPYSIGN:
                    $b = $this->stack->popF32();
                    $a = $this->stack->popF32();
                    $this->stack->push(Value::f32(Float32::copysign($a, $b)));
                    break;

                case Opcode::F32_ABS:
                    $this->stack->push(Value::f32(Float32::abs($this->stack->popF32())));
                    break;

                case Opcode::F32_NEG:
                    $this->stack->push(Value::f32(Float32::neg($this->stack->popF32())));
                    break;

                case Opcode::F32_CEIL:
                    $this->stack->push(Value::f32(Float32::ceil($this->stack->popF32())));
                    break;

                case Opcode::F32_FLOOR:
                    $this->stack->push(Value::f32(Float32::floor($this->stack->popF32())));
                    break;

                case Opcode::F32_TRUNC:
                    $this->stack->push(Value::f32(Float32::trunc($this->stack->popF32())));
                    break;

                case Opcode::F32_NEAREST:
                    $this->stack->push(Value::f32(Float32::nearest($this->stack->popF32())));
                    break;

                case Opcode::F32_EQ:
                    $b = $this->stack->popF32();
                    $a = $this->stack->popF32();
                    $this->stack->push(Value::i32(Float32::eq($a, $b)));
                    break;

                case Opcode::F32_NE:
                    $b = $this->stack->popF32();
                    $a = $this->stack->popF32();
                    $this->stack->push(Value::i32(Float32::ne($a, $b)));
                    break;

                case Opcode::F32_LT:
                    $b = $this->stack->popF32();
                    $a = $this->stack->popF32();
                    $this->stack->push(Value::i32(Float32::lt($a, $b)));
                    break;

                case Opcode::F32_GT:
                    $b = $this->stack->popF32();
                    $a = $this->stack->popF32();
                    $this->stack->push(Value::i32(Float32::gt($a, $b)));
                    break;

                case Opcode::F32_LE:
                    $b = $this->stack->popF32();
                    $a = $this->stack->popF32();
                    $this->stack->push(Value::i32(Float32::le($a, $b)));
                    break;

                case Opcode::F32_GE:
                    $b = $this->stack->popF32();
                    $a = $this->stack->popF32();
                    $this->stack->push(Value::i32(Float32::ge($a, $b)));
                    break;

                // f64 operations
                case Opcode::F64_ADD:
                    $b = $this->stack->popF64();
                    $a = $this->stack->popF64();
                    $this->stack->push(Value::f64(Float64::add($a, $b)));
                    break;

                case Opcode::F64_SUB:
                    $b = $this->stack->popF64();
                    $a = $this->stack->popF64();
                    $this->stack->push(Value::f64(Float64::sub($a, $b)));
                    break;

                case Opcode::F64_MUL:
                    $b = $this->stack->popF64();
                    $a = $this->stack->popF64();
                    $this->stack->push(Value::f64(Float64::mul($a, $b)));
                    break;

                case Opcode::F64_DIV:
                    $b = $this->stack->popF64();
                    $a = $this->stack->popF64();
                    $this->stack->push(Value::f64(Float64::div($a, $b)));
                    break;

                case Opcode::F64_SQRT:
                    $this->stack->push(Value::f64(Float64::sqrt($this->stack->popF64())));
                    break;

                case Opcode::F64_MIN:
                    $b = $this->stack->popF64();
                    $a = $this->stack->popF64();
                    $this->stack->push(Value::f64(Float64::min($a, $b)));
                    break;

                case Opcode::F64_MAX:
                    $b = $this->stack->popF64();
                    $a = $this->stack->popF64();
                    $this->stack->push(Value::f64(Float64::max($a, $b)));
                    break;

                case Opcode::F64_COPYSIGN:
                    $b = $this->stack->popF64();
                    $a = $this->stack->popF64();
                    $this->stack->push(Value::f64(Float64::copysign($a, $b)));
                    break;

                case Opcode::F64_ABS:
                    $this->stack->push(Value::f64(Float64::abs($this->stack->popF64())));
                    break;

                case Opcode::F64_NEG:
                    $this->stack->push(Value::f64(Float64::neg($this->stack->popF64())));
                    break;

                case Opcode::F64_CEIL:
                    $this->stack->push(Value::f64(Float64::ceil($this->stack->popF64())));
                    break;

                case Opcode::F64_FLOOR:
                    $this->stack->push(Value::f64(Float64::floor($this->stack->popF64())));
                    break;

                case Opcode::F64_TRUNC:
                    $this->stack->push(Value::f64(Float64::trunc($this->stack->popF64())));
                    break;

                case Opcode::F64_NEAREST:
                    $this->stack->push(Value::f64(Float64::nearest($this->stack->popF64())));
                    break;

                case Opcode::F64_EQ:
                    $b = $this->stack->popF64();
                    $a = $this->stack->popF64();
                    $this->stack->push(Value::i32(Float64::eq($a, $b)));
                    break;

                case Opcode::F64_NE:
                    $b = $this->stack->popF64();
                    $a = $this->stack->popF64();
                    $this->stack->push(Value::i32(Float64::ne($a, $b)));
                    break;

                case Opcode::F64_LT:
                    $b = $this->stack->popF64();
                    $a = $this->stack->popF64();
                    $this->stack->push(Value::i32(Float64::lt($a, $b)));
                    break;

                case Opcode::F64_GT:
                    $b = $this->stack->popF64();
                    $a = $this->stack->popF64();
                    $this->stack->push(Value::i32(Float64::gt($a, $b)));
                    break;

                case Opcode::F64_LE:
                    $b = $this->stack->popF64();
                    $a = $this->stack->popF64();
                    $this->stack->push(Value::i32(Float64::le($a, $b)));
                    break;

                case Opcode::F64_GE:
                    $b = $this->stack->popF64();
                    $a = $this->stack->popF64();
                    $this->stack->push(Value::i32(Float64::ge($a, $b)));
                    break;

                // Conversions
                case Opcode::I32_WRAP_I64:
                    $this->stack->push(Value::i32(Int32::toSigned($this->stack->popI64())));
                    break;

                case Opcode::I32_TRUNC_F32_S:
                    $this->stack->push(Value::i32(Float32::trunc_to_i32_s($this->stack->popF32())));
                    break;

                case Opcode::I32_TRUNC_F32_U:
                    $this->stack->push(Value::i32(Float32::trunc_to_i32_u($this->stack->popF32())));
                    break;

                case Opcode::I32_TRUNC_F64_S:
                    $this->stack->push(Value::i32(Float64::trunc_to_i32_s($this->stack->popF64())));
                    break;

                case Opcode::I32_TRUNC_F64_U:
                    $this->stack->push(Value::i32(Float64::trunc_to_i32_u($this->stack->popF64())));
                    break;

                case Opcode::I64_EXTEND_I32_S:
                    $this->stack->push(Value::i64(Int32::toSigned($this->stack->popI32())));
                    break;

                case Opcode::I64_EXTEND_I32_U:
                    $this->stack->push(Value::i64(Int32::toUnsigned($this->stack->popI32())));
                    break;

                case Opcode::I64_TRUNC_F32_S:
                    $this->stack->push(Value::i64(Float32::trunc_to_i64_s($this->stack->popF32())));
                    break;

                case Opcode::I64_TRUNC_F32_U:
                    $this->stack->push(Value::i64(Float32::trunc_to_i64_u($this->stack->popF32())));
                    break;

                case Opcode::I64_TRUNC_F64_S:
                    $this->stack->push(Value::i64(Float64::trunc_to_i64_s($this->stack->popF64())));
                    break;

                case Opcode::I64_TRUNC_F64_U:
                    $this->stack->push(Value::i64(Float64::trunc_to_i64_u($this->stack->popF64())));
                    break;

                case Opcode::F32_CONVERT_I32_S:
                    $this->stack->push(Value::f32(Float32::normalize((float)Int32::toSigned($this->stack->popI32()))));
                    break;

                case Opcode::F32_CONVERT_I32_U:
                    $this->stack->push(Value::f32(Float32::normalize((float)Int32::toUnsigned($this->stack->popI32()))));
                    break;

                case Opcode::F32_CONVERT_I64_S:
                    $this->stack->push(Value::f32(Float32::normalize((float)$this->stack->popI64())));
                    break;

                case Opcode::F32_CONVERT_I64_U:
                    $val = $this->stack->popI64();
                    $floatVal = (float)gmp_strval(Int64::toGmp($val));
                    $this->stack->push(Value::f32(Float32::normalize($floatVal)));
                    break;

                case Opcode::F32_DEMOTE_F64:
                    $this->stack->push(Value::f32(Float32::normalize($this->stack->popF64())));
                    break;

                case Opcode::F64_CONVERT_I32_S:
                    $this->stack->push(Value::f64((float)Int32::toSigned($this->stack->popI32())));
                    break;

                case Opcode::F64_CONVERT_I32_U:
                    $this->stack->push(Value::f64((float)Int32::toUnsigned($this->stack->popI32())));
                    break;

                case Opcode::F64_CONVERT_I64_S:
                    $this->stack->push(Value::f64((float)$this->stack->popI64()));
                    break;

                case Opcode::F64_CONVERT_I64_U:
                    $val = $this->stack->popI64();
                    $floatVal = (float)gmp_strval(Int64::toGmp($val));
                    $this->stack->push(Value::f64($floatVal));
                    break;

                case Opcode::F64_PROMOTE_F32:
                    $this->stack->push(Value::f64($this->stack->popF32()));
                    break;

                case Opcode::I32_REINTERPRET_F32:
                    $this->stack->push(Value::i32(BitCast::f32ToI32($this->stack->popF32())));
                    break;

                case Opcode::I64_REINTERPRET_F64:
                    $this->stack->push(Value::i64(BitCast::f64ToI64($this->stack->popF64())));
                    break;

                case Opcode::F32_REINTERPRET_I32:
                    $this->stack->push(Value::f32(BitCast::i32ToF32($this->stack->popI32())));
                    break;

                case Opcode::F64_REINTERPRET_I64:
                    $this->stack->push(Value::f64(BitCast::i64ToF64($this->stack->popI64())));
                    break;

                case Opcode::I32_EXTEND8_S:
                    $this->stack->push(Value::i32(Int32::extend8_s($this->stack->popI32())));
                    break;

                case Opcode::I32_EXTEND16_S:
                    $this->stack->push(Value::i32(Int32::extend16_s($this->stack->popI32())));
                    break;

                case Opcode::I64_EXTEND8_S:
                    $this->stack->push(Value::i64(Int64::extend8_s($this->stack->popI64())));
                    break;

                case Opcode::I64_EXTEND16_S:
                    $this->stack->push(Value::i64(Int64::extend16_s($this->stack->popI64())));
                    break;

                case Opcode::I64_EXTEND32_S:
                    $this->stack->push(Value::i64(Int64::extend32_s($this->stack->popI64())));
                    break;

                // Saturating conversions (Prefix 0xFC)
                case Opcode::I32_TRUNC_SAT_F32_S:
                    $this->stack->push(Value::i32(Float32::trunc_sat_to_i32_s($this->stack->popF32())));
                    break;

                case Opcode::I32_TRUNC_SAT_F32_U:
                    $this->stack->push(Value::i32(Float32::trunc_sat_to_i32_u($this->stack->popF32())));
                    break;

                case Opcode::I32_TRUNC_SAT_F64_S:
                    $this->stack->push(Value::i32(Float64::trunc_sat_to_i32_s($this->stack->popF64())));
                    break;

                case Opcode::I32_TRUNC_SAT_F64_U:
                    $this->stack->push(Value::i32(Float64::trunc_sat_to_i32_u($this->stack->popF64())));
                    break;

                case Opcode::I64_TRUNC_SAT_F32_S:
                    $this->stack->push(Value::i64(Float32::trunc_sat_to_i64_s($this->stack->popF32())));
                    break;

                case Opcode::I64_TRUNC_SAT_F32_U:
                    $this->stack->push(Value::i64(Float32::trunc_sat_to_i64_u($this->stack->popF32())));
                    break;

                case Opcode::I64_TRUNC_SAT_F64_S:
                    $this->stack->push(Value::i64(Float64::trunc_sat_to_i64_s($this->stack->popF64())));
                    break;

                case Opcode::I64_TRUNC_SAT_F64_U:
                    $this->stack->push(Value::i64(Float64::trunc_sat_to_i64_u($this->stack->popF64())));
                    break;

                default:
                    throw new RuntimeException(sprintf('Unimplemented instruction in VM: %s (0x%04X)', $ins->getMnemonic(), $op));
            }
        }

        array_pop($this->callStack);

        if ($returnArity === 0) {
            return null;
        }

        if ($returnArity === 1) {
            return $this->stack->pop()->raw;
        }

        $results = [];
        for ($i = 0; $i < $returnArity; $i++) {
            $results[] = $this->stack->pop()->raw;
        }
        return array_reverse($results);
    }

    /**
     * @return array{else: array<int, int>, end: array<int, int>}
     */
    private function getJumpTable(FunctionDef $def): array
    {
        $id = spl_object_id($def);
        if (isset($this->jumpTableCache[$id])) {
            return $this->jumpTableCache[$id];
        }

        $elseMap = [];
        $endMap = [];
        $stack = [];

        foreach ($def->body as $ip => $ins) {
            $op = $ins->opcode;

            if ($op === Opcode::BLOCK || $op === Opcode::LOOP || $op === Opcode::IF) {
                $stack[] = ['op' => $op, 'ip' => $ip, 'elseIp' => null];
            } elseif ($op === Opcode::ELSE) {
                if (!empty($stack)) {
                    $top = &$stack[count($stack) - 1];
                    $top['elseIp'] = $ip;
                    $elseMap[$top['ip']] = $ip;
                }
            } elseif ($op === Opcode::END) {
                if (!empty($stack)) {
                    $entry = array_pop($stack);
                    $endMap[$entry['ip']] = $ip;
                    if ($entry['elseIp'] !== null) {
                        $endMap[$entry['elseIp']] = $ip;
                    }
                }
            }
        }

        $table = ['else' => $elseMap, 'end' => $endMap];
        $this->jumpTableCache[$id] = $table;
        return $table;
    }
}
