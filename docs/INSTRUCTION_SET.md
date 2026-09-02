# Zenith Instruction Set Reference

Zenith supports the full WebAssembly 2.0 core instruction set, including 32-bit and 64-bit integer arithmetic, IEEE 754 floating-point operations, memory loads and stores, table operations, structured control flow, and non-trapping float-to-int conversion extensions.

---

## 1. Control Flow Instructions

| Opcode | Mnemonic | Immediates | Description |
| :--- | :--- | :--- | :--- |
| `0x00` | `unreachable` | none | Aborts execution immediately with an unreachable trap. |
| `0x01` | `nop` | none | No operation. |
| `0x02` | `block` | `blocktype` | Begins a structured forward block. |
| `0x03` | `loop` | `blocktype` | Begins a structured backward loop block. |
| `0x04` | `if` | `blocktype` | Pops `i32` condition; branches to `else` or past `end` if zero. |
| `0x05` | `else` | none | Delimits the alternate branch of an `if` block. |
| `0x0B` | `end` | none | Terminates a structured control block or function body. |
| `0x0C` | `br` | `labelidx` | Unconditionally branches to the target label depth. |
| `0x0D` | `br_if` | `labelidx` | Pops `i32` condition; branches to label depth if non-zero. |
| `0x0E` | `br_table` | `vec(labelidx), labelidx` | Indirect jump table based on index operand. |
| `0x0F` | `return` | none | Returns from current function call frame. |
| `0x10` | `call` | `funcidx` | Calls a defined or imported function by index. |
| `0x11` | `call_indirect`| `typeidx, tableidx` | Calls a function pointer from a table matching type signature. |

---

## 2. Parametric Instructions

| Opcode | Mnemonic | Description |
| :--- | :--- | :--- |
| `0x1A` | `drop` | Discards the topmost value on the operand stack. |
| `0x1B` | `select` | Pops condition and two operands of same type; returns selected value. |
| `0x1C` | `select t*` | Typed variant of `select` supporting reference types. |

---

## 3. Variable and Global Instructions

| Opcode | Mnemonic | Immediates | Description |
| :--- | :--- | :--- | :--- |
| `0x20` | `local.get` | `localidx` | Pushes the value of a local variable onto the stack. |
| `0x21` | `local.set` | `localidx` | Pops a value from the stack and stores it in a local variable. |
| `0x22` | `local.tee` | `localidx` | Stores top value to local variable without popping it. |
| `0x23` | `global.get`| `globalidx`| Pushes the value of a global variable onto the stack. |
| `0x24` | `global.set`| `globalidx`| Pops a value from the stack and updates a mutable global. |

---

## 4. Memory Instructions

Loads and stores operate on linear memory 0 using little-endian byte ordering. Immediate values specify alignment hints and constant memory offsets.

| Family | Mnemonic Examples | Description |
| :--- | :--- | :--- |
| **Loads** | `i32.load`, `i64.load`, `f32.load`, `f64.load` | Full-width primitive loads with byte offset. |
| **Sub-word Loads** | `i32.load8_s`, `i32.load8_u`, `i32.load16_s`, `i32.load16_u` | Signed/unsigned 8-bit and 16-bit loads into 32-bit registers. |
| **64-bit Sub-loads** | `i64.load8_s`, `i64.load8_u`, `i64.load16_s`, `i64.load32_u` | Sub-word loads zero- or sign-extended into 64-bit integers. |
| **Stores** | `i32.store`, `i64.store`, `f32.store`, `f64.store` | Full-width primitive stores. |
| **Sub-word Stores** | `i32.store8`, `i32.store16`, `i64.store8`, `i64.store32` | Truncates value and writes lowest bytes to memory. |
| **Memory Management**| `memory.size`, `memory.grow` | Queries page size or allocates additional 64 KiB memory pages. |

---

## 5. Numeric ALU Operations

### 32-Bit & 64-Bit Integers
- **Arithmetic**: `add`, `sub`, `mul`, `div_s`, `div_u`, `rem_s`, `rem_u`
- **Bitwise Logic**: `and`, `or`, `xor`, `shl`, `shr_s`, `shr_u`, `rotl`, `rotr`
- **Bit Counting**: `clz` (count leading zeros), `ctz` (count trailing zeros), `popcnt` (population count)
- **Comparisons**: `eqz`, `eq`, `ne`, `lt_s`, `lt_u`, `gt_s`, `gt_u`, `le_s`, `le_u`, `ge_s`, `ge_u`

### 32-Bit & 64-Bit Floating Point (IEEE 754)
- **Arithmetic**: `add`, `sub`, `mul`, `div`, `sqrt`
- **Rounding & Shaping**: `abs`, `neg`, `ceil`, `floor`, `trunc`, `nearest`, `copysign`
- **Extrema**: `min`, `max` (strict Wasm NaN semantics)
- **Comparisons**: `eq`, `ne`, `lt`, `gt`, `le`, `ge`

---

## 6. Type Conversions & Saturating Truncations

- **Sign Extension**: `i32.extend8_s`, `i32.extend16_s`, `i64.extend8_s`, `i64.extend16_s`, `i64.extend32_s`
- **Width Promotion / Demotion**: `i64.extend_i32_s`, `i64.extend_i32_u`, `i32.wrap_i64`, `f64.promote_f32`, `f32.demote_f64`
- **Floating-Point to Integer**: `i32.trunc_f32_s`, `i32.trunc_f32_u`, `i32.trunc_f64_s`, `i32.trunc_f64_u` (with traps on overflow or NaN)
- **Saturating Conversions (Prefix `0xFC`)**: `i32.trunc_sat_f32_s`, `i32.trunc_sat_f32_u`, etc. Clamps to integer boundaries without trapping.
- **Bitwise Reinterpretation**: `i32.reinterpret_f32`, `f32.reinterpret_i32`, `i64.reinterpret_f64`, `f64.reinterpret_i64`
