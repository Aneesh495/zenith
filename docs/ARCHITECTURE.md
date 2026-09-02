# Zenith Architecture & Subsystem Internals

This document details the internal design, execution pipeline, and state representation of the Zenith WebAssembly 2.0 virtual machine.

---

## 1. Pipeline Overview

Zenith processes WebAssembly modules through five key stages:

```
[ Input: .wasm / .wat ]
          |
          v
  1. Lexing & Parsing / Binary Decoding
          |
          v
  2. AST Construction & Section Synthesis
          |
          v
  3. Formal Type & Stack Validation
          |
          v
  4. Jump Table Generation & Instantiation
          |
          v
  5. Stack Machine Execution Loop
```

---

## 2. The Abstract Syntax Tree (`Zenith\Ast`)

The internal module AST mirrors the formal structure defined by the WebAssembly specification:

- **`Module`**: Top-level container holding types, imports, functions, tables, memories, globals, exports, start index, element segments, data segments, and custom sections.
- **`FunctionDef`**: Represents a defined function. Stores its signature type index, declared locals vector, instruction list, and debug symbol metadata.
- **`Instruction`**: Uniform representation of an opcode with an associated immediate value (e.g. index, type, branch depth, numeric literal, or memory offset).

---

## 3. The Virtual Machine Core (`Zenith\Runtime\Vm`)

### Execution Loop & Dispatch

The core interpreter loop in `Zenith\Runtime\Vm::callFunction` dispatches instructions using a native PHP `switch-case` construct. For modern PHP with OPcache and JIT enabled, this compiles into a direct jump table or indexed branch dispatch.

### Jump Table Compilation

Standard Wasm implementations often resolve branch targets dynamically at runtime by scanning instructions or unwinding structured blocks. Zenith instead precomputes all branch jump targets during function instantiation.

When a function is prepared for execution:
1. `Vm::getJumpTable` scans the bytecode stream in a single forward pass.
2. Control structures (`block`, `loop`, `if`, `else`, `end`) push entries onto an indexing stack.
3. Every `BLOCK` and `IF` record their matching `END` and `ELSE` instruction pointer offsets.
4. On `br`, `br_if`, or `br_table`, the target instruction pointer is resolved in `O(1)` time by consulting the precomputed table.

```
Instruction Index:
  [0] block
  [1]   local.get 0
  [2]   br_if 0 ----( jumps directly to index 5 without scanning )----+
  [3]   local.get 1                                                   |
  [4]   i32.add                                                       |
  [5] end <-----------------------------------------------------------+
```

### Control Label Stack

Each active function call frame maintains a label stack representing active structured blocks:
- **`instructionPointer`**: The continuation target address to resume when jumping to this label.
- **`arity`**: Number of return values expected when exiting or continuing through this label.
- **`stackHeight`**: The operand stack height when entering the control structure. Unwinding resets the stack back to this height while preserving return values.
- **`isLoop`**: Differentiates backward loops from forward blocks.

---

## 4. Operand Stack (`Zenith\Runtime\Stack`)

The operand stack stores `Zenith\Runtime\Value` objects. To maintain maximum throughput and avoid memory allocations during inner loops:
- Primitive pops (`popI32`, `popI64`, `popF32`, `popF64`) unpack the raw PHP values directly.
- The `unwind(targetHeight, arity)` method efficiently preserves the topmost `arity` values while truncating intermediate operands created within nested blocks.

---

## 5. Memory and Host Environment

WebAssembly modules are isolated from the host machine:
- Host functions are registered in `Zenith\Runtime\Store` using `registerHostFunction`.
- When an instruction invokes `call` targeting an imported function index, the interpreter dispatches to the host closure.
- The host closure receives the active `Instance` reference, allowing safe access to linear memory to read string buffers or write syscall outputs.
