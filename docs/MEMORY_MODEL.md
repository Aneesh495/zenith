# Zenith Linear Memory & Paging Model

This document outlines the linear memory architecture, paging mechanics, endianness handling, and trap conditions enforced by Zenith.

---

## 1. Paging Architecture

WebAssembly linear memory is organized as an unsegmented contiguous array of raw bytes partitioned into fixed-size pages:

- **Page Size**: Exactly 65,536 bytes (64 KiB).
- **Initial Size**: Defined by the module's memory section (`limits.min`).
- **Maximum Limit**: Optionally defined by `limits.max`. The WebAssembly specification imposes an absolute maximum ceiling of 65,536 pages (4 GiB).

In Zenith, memory is backed by a native PHP string buffer (`$this->buffer`) in `Zenith\Runtime\Memory`. Native PHP strings provide fast, contiguous, byte-level memory addressing and slice mutations via `substr` and binary packing.

```
Page 0: [ 0x00000000 .. 0x0000FFFF ] (64 KiB)
Page 1: [ 0x00010000 .. 0x0001FFFF ] (64 KiB)
...
Page N: [ N * 65536  .. (N+1)*65536 - 1 ]
```

---

## 2. Dynamic Memory Growth (`memory.grow`)

Modules dynamically allocate additional memory using the `memory.grow` instruction:

1. Takes a single `i32` operand specifying the number of additional pages (`delta`).
2. If `delta` is zero, returns the current page count without mutating the buffer.
3. If `current_pages + delta` exceeds the declared maximum limit or 65,536 pages, the operation fails and pushes `-1` onto the operand stack.
4. On success, memory is expanded with zero-initialized bytes (`\0`), and the previous page count is pushed onto the stack.

---

## 3. Endianness and Alignment

WebAssembly linear memory is strictly little-endian:

- **16-bit values**: Least significant byte stored at `offset + 0`, most significant byte at `offset + 1`.
- **32-bit values**: Low byte at `offset + 0`, high byte at `offset + 3`.
- **64-bit values**: Unpacked using native 64-bit little-endian binary formats (`P`, `q`).

Alignment hints provided in bytecode instructions are validated during compilation. Zenith supports unaligned memory access in accordance with the WebAssembly specification, ensuring identical execution semantics across all platforms.

---

## 4. Bounds Checking and Traps

Every memory access validates that `effective_address + access_length <= total_byte_length`.

If any portion of a read or write operation extends beyond allocated memory:
- Execution terminates immediately.
- A `Zenith\Runtime\Trap` is thrown with code `OUT_OF_BOUNDS_MEMORY`.
- The current call frame is aborted, preventing memory corruption.

---

## 5. Bulk Memory Operations (WebAssembly 2.0)

Zenith supports bulk memory copy and fill primitives:
- **`memory.fill`**: Sets a range of bytes to a given byte value in linear memory.
- **`memory.copy`**: Copies a block of bytes from a source offset to a destination offset with safe handling for overlapping memory ranges.
