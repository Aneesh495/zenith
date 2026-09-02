# Zenith WASI Snapshot Preview 1 Specification Implementation

This document details the implementation of the WebAssembly System Interface (WASI) Preview 1 subsystem within Zenith.

---

## 1. Overview

WASI Preview 1 (`wasi_snapshot_preview1`) defines a modular POSIX-like system interface for WebAssembly programs. Zenith implements these host functions directly in PHP, mapping Wasm linear memory structures to host I/O operations without requiring PHP POSIX extensions.

Host functions are registered under the module namespace `wasi_snapshot_preview1` in `Zenith\Runtime\Store`.

---

## 2. Implemented Syscalls

### File Descriptors & I/O

- **`fd_write(fd: u32, iovs: *const ciovec, iovs_len: u32, nwritten: *mut u32) -> errno`**
  Writes data from vectored scatter/gather buffers to the specified file descriptor.
  Each `ciovec` struct is an 8-byte record: `[buf_ptr: u32, buf_len: u32]`.
  Returns `0` (`SUCCESS`) on success, or `EBADF`/`EFAULT` on invalid descriptor or memory out of bounds.

- **`fd_read(fd: u32, iovs: *const iovec, iovs_len: u32, nread: *mut u32) -> errno`**
  Reads data into vectored buffers from the specified file descriptor.

- **`fd_seek(fd: u32, offset: s64, whence: u8, newoffset: *mut u64) -> errno`**
  Sets the stream position of a file descriptor relative to start, current, or end of file.

- **`fd_tell(fd: u32, newoffset: *mut u64) -> errno`**
  Returns the current stream position of a file descriptor.

- **`fd_close(fd: u32) -> errno`**
  Closes an active file descriptor and releases host resources.

- **`fd_fdstat_get(fd: u32, stat: *mut fdstat) -> errno`**
  Returns metadata and access rights for an open file descriptor.

- **`fd_prestat_get(fd: u32, prestat: *mut prestat) -> errno`**
- **`fd_prestat_dir_name(fd: u32, path: *mut u8, path_len: u32) -> errno`**
  Exposes preopened directories for sandboxed file system capabilities.

---

### Process Environment & Arguments

- **`args_sizes_get(argc: *mut u32, argv_buf_size: *mut u32) -> errno`**
  Calculates the total number of command line arguments and required byte buffer size.

- **`args_get(argv: *mut *mut u8, argv_buf: *mut u8) -> errno`**
  Populates argument pointer tables and null-terminated string buffers in linear memory.

- **`environ_sizes_get(environ_count: *mut u32, environ_buf_size: *mut u32) -> errno`**
- **`environ_get(environ: *mut *mut u8, environ_buf: *mut u8) -> errno`**
  Populates environment variable string tables (`KEY=VALUE`).

---

### Clocks & Entropy

- **`clock_time_get(id: u32, precision: u64, time: *mut u64) -> errno`**
  Returns current time in nanoseconds for clock IDs:
  - `CLOCK_REALTIME (0)`: Wall-clock time derived from `microtime(true)`.
  - `CLOCK_MONOTONIC (1)`: Monotonic high-resolution timer from `hrtime(true)`.
  - `CLOCK_PROCESS_CPUTIME_ID (2)`: CPU time consumed by calling process.

- **`clock_res_get(id: u32, resolution: *mut u64) -> errno`**
  Returns the nanosecond resolution of the requested clock.

- **`random_get(buf: *mut u8, buf_len: u32) -> errno`**
  Fills a memory buffer with cryptographically secure random bytes via `random_bytes()`.

---

### Lifecycle

- **`proc_exit(rval: u32)`**
  Terminates WebAssembly execution immediately by throwing a `Zenith\Wasi\WasiExitException` carrying the exit code.

---

## 3. Error Codes (`Zenith\Wasi\WasiErrno`)

All WASI syscalls return a 16-bit status code matching canonical WASI definitions:
- `SUCCESS (0)`: Operation completed normally.
- `EBADF (8)`: Invalid file descriptor.
- `EFAULT (21)`: Memory address outside linear memory bounds.
- `EINVAL (28)`: Invalid argument or parameter.
- `ENOSYS (52)`: Unsupported or unimplemented capability.
