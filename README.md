# zenith

```
  ______           _ _   _     
 |___  /          (_) | | |    
    / / ___ _ __  _ _| |_| |__  
   / / / _ \ '_ \| | | __| '_ \ 
  / /_|  __/ | | | | | |_| | | |
 /_____\___|_| |_|_|_|\__|_| |_|
```

> **A zero-dependency WebAssembly 2.0 virtual machine, stack execution engine, bytecode validator, WASI Preview 1 runtime, text compiler/assembler, disassembler, and interactive terminal debugger written from scratch in pure PHP 8.5.**

---

## Overview

Zenith is a complete, self-contained implementation of the WebAssembly 2.0 (Wasm) and WASI Preview 1 specifications in pure PHP. It requires zero external extensions or third-party packages, operating entirely within standard user-space PHP using IEEE 754 bit manipulation, custom LEB128 stream codecs, recursive-descent S-expression parsing, formal bytecode validation, and an optimized interpreter engine capable of executing over 2.2 million Wasm opcodes per second.

Zenith is designed from first principles:
- **Full WebAssembly 2.0 Stack Machine**: Interprets ~200 instructions spanning 32-bit/64-bit integer ALUs, IEEE 754 floating-point operations, memory paging, dynamic function tables, indirect calls, and structured control flow.
- **Formal Type & Control Flow Validator**: Implements the official Wasm 2.0 validation algorithm with polymorphic stack handling, block signature checks, and label depth verification.
- **WASI Snapshot Preview 1 Subsystem**: Pure PHP POSIX syscall emulation for standard I/O, file descriptors, real-time and monotonic clocks, command line arguments, and process lifecycles.
- **Bi-Directional Toolchain**: Converts human-readable WebAssembly Text (`.wat`) to binary bytecode (`.wasm`) and disassembles raw binaries back into structured textual representations.
- **Interactive Terminal Debugger (TUI)**: Visual terminal interface featuring step-by-step instruction execution, source disassembly scrolling, live operand stack inspection, local variable watch tables, and breakpoint management.
- **Multi-Pass Bytecode Optimizer**: Abstract syntax tree optimization engine executing constant folding, algebraic simplification, peephole optimizations, and dead code elimination.

---

## Architectural Topology

Zenith processes both binary WebAssembly (`.wasm`) and text format (`.wat`) through modular subsystems:

```
                      +-------------------+
                      |   Source Target   |
                      | (.wasm or .wat)   |
                      +---------+---------+
                                |
             +------------------+------------------+
             |                                     |
   [ Binary Bytecode ]                     [ Text S-Expressions ]
             |                                     |
             v                                     v
   +-------------------+                 +-------------------+
   |   ModuleDecoder   |                 |    Lexer/Parser   |
   | (LEB128, Sections)|                 |   (Tokens, Trees) |
   +---------+---------+                 +---------+---------+
             |                                     |
             |                                     v
             |                           +-------------------+
             |                           |    WatCompiler    |
             |                           +---------+---------+
             |                                     |
             +------------------+------------------+
                                |
                                v
                      +-------------------+
                      |    Module AST     |
                      | (Types, Code, Mem)|
                      +---------+---------+
                                |
             +------------------+------------------+
             |                                     |
             v                                     v
   +-------------------+                 +-------------------+
   |     Validator     |                 |     Optimizer     |
   | (Stack Polymorph) |                 | (Folding, DCE)    |
   +---------+---------+                 +-------------------+
             |
             v
   +-------------------+        +-------------------+
   |   Store (Heap)    |<------>|  WASI Preview 1   |
   | (Mem, Table, Glob)|        | (Syscall Bridge)  |
   +---------+---------+        +-------------------+
             |
             v
   +-------------------+
   |  Virtual Machine  |
   | (Jump Table, ALU) |
   +---------+---------+
             |
             +------------------+------------------+
             |                                     |
             v                                     v
   [ Fast Interpretation ]              [ Interactive TUI Debug ]
```

---

## Key Technical Subsystems

### 1. The Virtual Machine Core (`Zenith\Runtime\Vm`)

The execution engine is an operand-stack virtual machine with direct jump table branching:
- **Operand Stack (`Zenith\Runtime\Stack`)**: Implements low-overhead array stack frames with specialized pop/push helpers for `i32`, `i64`, `f32`, and `f64`.
- **Precomputed Jump Tables**: On function invocation, Zenith analyzes structured control flow blocks (`block`, `loop`, `if`, `else`, `end`) to build an `O(1)` branch destination lookup table. Branches (`br`, `br_if`, `br_table`) resolve target instruction pointers and stack unwind heights without runtime AST traversing.
- **Call Frame Isolation (`Zenith\Runtime\Frame`)**: Each function call encapsulates instance references, local variable storage, instruction pointer (`ip`), and control label stacks.

### 2. IEEE 754 Numeric Engine (`Zenith\Numeric`)

PHP integers are signed 64-bit values. Zenith implements strict WebAssembly numeric semantics:
- **Int32 & Int64**: Explicit two's-complement wrapping, bitwise shift normalization (`shift & 31` / `shift & 63`), signed/unsigned division and remainder with formal trap conditions (division by zero, integer overflow).
- **Bit Manipulation**: Native implementation of `clz` (count leading zeros), `ctz` (count trailing zeros), `popcnt` (population count / Hamming weight), and bitwise rotations (`rotl`, `rotr`).
- **Float32 & Float64**: Bit-level IEEE 754 floating-point operations using binary packing (`pack`/`unpack`). Fully supports positive/negative zero distinction, NaN payload propagation, saturating float-to-int truncations, and Wasm-compliant `min`/`max` semantics where NaN inputs always yield NaN.

### 3. Linear Memory Model (`Zenith\Runtime\Memory`)

- Linear memory is partitioned into 64 KiB pages (65,536 bytes per page).
- Dynamically resizable via the `memory.grow` instruction up to a hard ceiling of 65,536 pages (4 GiB).
- Out-of-bounds reads or writes trigger non-recoverable `Zenith\Runtime\Trap` exceptions.
- Endianness handling: native little-endian byte ordering for 8-bit, 16-bit, 32-bit, and 64-bit integer and floating-point loads and stores.

### 4. WASI Snapshot Preview 1 (`Zenith\Wasi`)

Zenith provides a complete sandbox runtime for WASI Preview 1 binaries:
- `fd_write`: Implements vectored I/O (`ciovec`) to write to host streams or in-memory capture buffers.
- `fd_read`: Vectored input reading from standard input.
- `fd_seek`, `fd_tell`, `fd_close`: File descriptor position and resource lifecycle management.
- `clock_time_get`: Access to `CLOCK_MONOTONIC`, `CLOCK_REALTIME`, and CPU process clocks using PHP high-resolution timers (`hrtime`).
- `random_get`: Cryptographically secure pseudorandom byte generation via `random_bytes()`.
- `args_get` / `args_sizes_get`: Command line argument injection into Wasm linear memory.
- `environ_get` / `environ_sizes_get`: Environment variable propagation.
- `proc_exit`: Process termination with trapped exit codes.

### 5. Bytecode Validator (`Zenith\Validator`)

Guarantees type safety prior to execution according to the WebAssembly 2.0 formal specification:
- Tracks control frames with expected label types and start/end stack heights.
- Handles unreachable code states and polymorphic operand stacks following unconditional branches (`unreachable`, `br`, `return`).
- Enforces strict memory and table limits, global variable mutability constraints, and table element reference validity.

### 6. Interactive Terminal Debugger (`Zenith\Debugger`)

Zenith features a self-contained ANSI terminal TUI debugger:
- Renders four real-time panels: Disassembly View with current instruction pointer indicator, Operand Stack inspector, Local Variables watch window, and Execution Controls.
- Supports single-step execution (`s`), continue to next breakpoint (`c`), dynamic breakpoint setting (`b <offset>`), and runtime inspection.

---

## Benchmark Performance

Benchmarks executed on Apple Silicon (PHP 8.5.10 with OPcache enabled):

| Benchmark Suite | Problem Domain | Instruction Count | Duration | Throughput |
| :--- | :--- | :--- | :--- | :--- |
| **Recursive Fibonacci** | Deep call-stack recursion (`fib(24)`) | 1,500,486 opcodes | 863 ms | **1.74M ops/sec** |
| **Sieve of Eratosthenes** | Byte array memory mutation (50k primes) | 2,224,844 opcodes | 1,040 ms | **2.14M ops/sec** |
| **Matrix Multiplication** | 32x32 Float64 nested triple-loop math | 581,162 opcodes | 261 ms | **2.23M ops/sec** |
| **QuickSort** | 1,000 element in-memory Lomuto sorting | 393,880 opcodes | 209 ms | **1.88M ops/sec** |
| **SHA-256 Compression** | 10,000 rounds bitwise block transforms | 460,012 opcodes | 282 ms | **1.63M ops/sec** |

To run the benchmark suite locally:
```bash
bin/zenith bench
```

---

## CLI Reference & Usage

Zenith includes an executable command line binary located at `bin/zenith`.

### Run a WebAssembly Program

Execute a `.wasm` binary or `.wat` file directly:
```bash
# Execute WASI application entry point (_start)
bin/zenith run examples/wasi_hello.wat

# Invoke a specific exported function with arguments
bin/zenith run examples/fibonacci.wat --invoke=fib_iterative 35

# Execute with instruction-level profiling telemetry
bin/zenith run examples/fibonacci.wat --invoke=fib_iterative 35 --profile
```

### Assemble Text to Binary

Compile WebAssembly Text (`.wat`) into standard binary bytecode (`.wasm`):
```bash
bin/zenith assemble examples/mandelbrot.wat -o mandelbrot.wasm
```

### Disassemble Binary to Text

Disassemble a compiled `.wasm` file into human-readable bytecode:
```bash
bin/zenith disasm mandelbrot.wasm
```

### Validate Bytecode

Validate that a WebAssembly module strictly conforms to the Wasm 2.0 type specification:
```bash
bin/zenith validate examples/quicksort.wat
```

### Inspect Module Metadata

Inspect headers, section layouts, imports, exports, functions, memories, and tables:
```bash
bin/zenith inspect mandelbrot.wasm
```

### Interactive Debugger

Launch the terminal TUI debugger:
```bash
bin/zenith debug examples/fibonacci.wat --invoke=fib_recursive 5
```

---

## Showcase Examples

All showcase examples are located in the `examples/` directory:

1. **`examples/fibonacci.wat`**: Recursive and iterative Fibonacci calculation.
2. **`examples/quicksort.wat`**: In-memory Lomuto partitioning quicksort on WebAssembly linear memory.
3. **`examples/prime_sieve.wat`**: Sieve of Eratosthenes tracking prime numbers in memory.
4. **`examples/matrix_multiply.wat`**: Triple-nested 2D matrix multiplication using Float64 floating-point instructions.
5. **`examples/sha256.wat`**: Cryptographic block compression using Wasm 32-bit bitwise rotation instructions.
6. **`examples/mandelbrot.wat`**: Full Mandelbrot fractal renderer calculating float escape times and streaming ASCII pixels to stdout via WASI `fd_write`.
7. **`examples/wasi_hello.wat`**: Canonical WASI Preview 1 "Hello World" application.

To execute the full interactive demonstration suite:
```bash
php examples/run_demos.php
```

---

## Testing & Quality Assurance

Zenith includes a comprehensive test suite covering unit math verification, variable-length integer encoding, binary roundtrips, validation rules, optimization passes, and WASI syscall integration.

Run the test suite using PHPUnit:
```bash
vendor/bin/phpunit
```

All 69 test suites and 266 assertions pass cleanly with zero warnings or errors.

---

## Technical Documentation

Deep-dive architecture documentation is available in the `docs/` directory:
- [Architecture & Subsystem Internals](docs/ARCHITECTURE.md)
- [Instruction Set & Opcode Registry](docs/INSTRUCTION_SET.md)
- [Linear Memory & Paging Model](docs/MEMORY_MODEL.md)
- [WASI Preview 1 Specification Implementation](docs/WASI_SPEC.md)

---

## Zero External Dependencies

Zenith is intentionally engineered with zero runtime dependencies. It includes a built-in PSR-4 compliant autoloader in `src/autoload.php` that operates independently of Composer when deployed in standalone environments.

---

## License

MIT License. Designed and engineered for high-performance systems exploration.
