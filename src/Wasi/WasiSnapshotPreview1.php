<?php

declare(strict_types=1);

namespace Zenith\Wasi;

use Zenith\Ast\FuncType;
use Zenith\Ast\ValueType;
use Zenith\Runtime\Instance;
use Zenith\Runtime\Store;

/**
 * Complete WASI (WebAssembly System Interface) Preview 1 host implementation.
 *
 * Implements POSIX-like system calls for stdio, scatter/gather I/O,
 * arguments, environment variables, nanosecond timers, and entropy generation.
 */
final class WasiSnapshotPreview1
{
    public const MODULE_NAME = 'wasi_snapshot_preview1';

    public static function bind(Store $store, WasiContext $context): void
    {
        $w = new self($context);

        // args_sizes_get(argc: *mut u32, argv_buf_size: *mut u32) -> errno
        $store->registerHostFunction(
            self::MODULE_NAME,
            'args_sizes_get',
            new FuncType([ValueType::I32, ValueType::I32], [ValueType::I32]),
            fn(Instance $inst, int $argcPtr, int $bufSizePtr): int => $w->argsSizesGet($inst, $argcPtr, $bufSizePtr)
        );

        // args_get(argv: *mut *mut u8, argv_buf: *mut u8) -> errno
        $store->registerHostFunction(
            self::MODULE_NAME,
            'args_get',
            new FuncType([ValueType::I32, ValueType::I32], [ValueType::I32]),
            fn(Instance $inst, int $argvPtr, int $argvBufPtr): int => $w->argsGet($inst, $argvPtr, $argvBufPtr)
        );

        // environ_sizes_get(count: *mut u32, buf_size: *mut u32) -> errno
        $store->registerHostFunction(
            self::MODULE_NAME,
            'environ_sizes_get',
            new FuncType([ValueType::I32, ValueType::I32], [ValueType::I32]),
            fn(Instance $inst, int $countPtr, int $bufSizePtr): int => $w->environSizesGet($inst, $countPtr, $bufSizePtr)
        );

        // environ_get(environ: *mut *mut u8, environ_buf: *mut u8) -> errno
        $store->registerHostFunction(
            self::MODULE_NAME,
            'environ_get',
            new FuncType([ValueType::I32, ValueType::I32], [ValueType::I32]),
            fn(Instance $inst, int $environPtr, int $environBufPtr): int => $w->environGet($inst, $environPtr, $environBufPtr)
        );

        // clock_res_get(id: u32, resolution: *mut u64) -> errno
        $store->registerHostFunction(
            self::MODULE_NAME,
            'clock_res_get',
            new FuncType([ValueType::I32, ValueType::I32], [ValueType::I32]),
            fn(Instance $inst, int $clockId, int $resPtr): int => $w->clockResGet($inst, $clockId, $resPtr)
        );

        // clock_time_get(id: u32, precision: u64, time: *mut u64) -> errno
        $store->registerHostFunction(
            self::MODULE_NAME,
            'clock_time_get',
            new FuncType([ValueType::I32, ValueType::I64, ValueType::I32], [ValueType::I32]),
            fn(Instance $inst, int $clockId, int $precision, int $timePtr): int => $w->clockTimeGet($inst, $clockId, $precision, $timePtr)
        );

        // fd_write(fd: u32, iovs: *const ciovec, iovs_len: u32, nwritten: *mut u32) -> errno
        $store->registerHostFunction(
            self::MODULE_NAME,
            'fd_write',
            new FuncType([ValueType::I32, ValueType::I32, ValueType::I32, ValueType::I32], [ValueType::I32]),
            fn(Instance $inst, int $fd, int $iovsPtr, int $iovsLen, int $nwrittenPtr): int => $w->fdWrite($inst, $fd, $iovsPtr, $iovsLen, $nwrittenPtr)
        );

        // fd_read(fd: u32, iovs: *const iovec, iovs_len: u32, nread: *mut u32) -> errno
        $store->registerHostFunction(
            self::MODULE_NAME,
            'fd_read',
            new FuncType([ValueType::I32, ValueType::I32, ValueType::I32, ValueType::I32], [ValueType::I32]),
            fn(Instance $inst, int $fd, int $iovsPtr, int $iovsLen, int $nreadPtr): int => $w->fdRead($inst, $fd, $iovsPtr, $iovsLen, $nreadPtr)
        );

        // fd_seek(fd: u32, offset: s64, whence: u32, newoffset: *mut u64) -> errno
        $store->registerHostFunction(
            self::MODULE_NAME,
            'fd_seek',
            new FuncType([ValueType::I32, ValueType::I64, ValueType::I32, ValueType::I32], [ValueType::I32]),
            fn(Instance $inst, int $fd, int $offset, int $whence, int $newOffsetPtr): int => $w->fdSeek($inst, $fd, $offset, $whence, $newOffsetPtr)
        );

        // fd_close(fd: u32) -> errno
        $store->registerHostFunction(
            self::MODULE_NAME,
            'fd_close',
            new FuncType([ValueType::I32], [ValueType::I32]),
            fn(Instance $inst, int $fd): int => $w->fdClose($inst, $fd)
        );

        // fd_fdstat_get(fd: u32, stat: *mut fdstat) -> errno
        $store->registerHostFunction(
            self::MODULE_NAME,
            'fd_fdstat_get',
            new FuncType([ValueType::I32, ValueType::I32], [ValueType::I32]),
            fn(Instance $inst, int $fd, int $statPtr): int => $w->fdFdstatGet($inst, $fd, $statPtr)
        );

        // fd_prestat_get(fd: u32, prestat: *mut prestat) -> errno
        $store->registerHostFunction(
            self::MODULE_NAME,
            'fd_prestat_get',
            new FuncType([ValueType::I32, ValueType::I32], [ValueType::I32]),
            fn(Instance $inst, int $fd, int $bufPtr): int => $w->fdPrestatGet($inst, $fd, $bufPtr)
        );

        // fd_prestat_dir_name(fd: u32, path: *mut u8, path_len: u32) -> errno
        $store->registerHostFunction(
            self::MODULE_NAME,
            'fd_prestat_dir_name',
            new FuncType([ValueType::I32, ValueType::I32, ValueType::I32], [ValueType::I32]),
            fn(Instance $inst, int $fd, int $pathPtr, int $pathLen): int => $w->fdPrestatDirName($inst, $fd, $pathPtr, $pathLen)
        );

        // random_get(buf: *mut u8, buf_len: u32) -> errno
        $store->registerHostFunction(
            self::MODULE_NAME,
            'random_get',
            new FuncType([ValueType::I32, ValueType::I32], [ValueType::I32]),
            fn(Instance $inst, int $bufPtr, int $bufLen): int => $w->randomGet($inst, $bufPtr, $bufLen)
        );

        // proc_exit(rval: u32)
        $store->registerHostFunction(
            self::MODULE_NAME,
            'proc_exit',
            new FuncType([ValueType::I32], []),
            fn(Instance $inst, int $rval): mixed => $w->procExit($inst, $rval)
        );

        // sched_yield() -> errno
        $store->registerHostFunction(
            self::MODULE_NAME,
            'sched_yield',
            new FuncType([], [ValueType::I32]),
            fn(Instance $inst): int => WasiErrno::SUCCESS
        );
    }

    public function __construct(
        private readonly WasiContext $context
    ) {
    }

    public function argsSizesGet(Instance $inst, int $argcPtr, int $bufSizePtr): int
    {
        $mem = $inst->getMemory(0);
        if ($mem === null) {
            return WasiErrno::EFAULT;
        }

        $argc = count($this->context->args);
        $totalBytes = 0;
        foreach ($this->context->args as $arg) {
            $totalBytes += strlen($arg) + 1; // null-terminated
        }

        $mem->writeI32($argcPtr, $argc);
        $mem->writeI32($bufSizePtr, $totalBytes);

        return WasiErrno::SUCCESS;
    }

    public function argsGet(Instance $inst, int $argvPtr, int $argvBufPtr): int
    {
        $mem = $inst->getMemory(0);
        if ($mem === null) {
            return WasiErrno::EFAULT;
        }

        $curBuf = $argvBufPtr;
        foreach ($this->context->args as $i => $arg) {
            $mem->writeI32($argvPtr + ($i * 4), $curBuf);
            $mem->writeBytes($curBuf, $arg . "\0");
            $curBuf += strlen($arg) + 1;
        }

        return WasiErrno::SUCCESS;
    }

    public function environSizesGet(Instance $inst, int $countPtr, int $bufSizePtr): int
    {
        $mem = $inst->getMemory(0);
        if ($mem === null) {
            return WasiErrno::EFAULT;
        }

        $count = count($this->context->environ);
        $totalBytes = 0;
        foreach ($this->context->environ as $k => $v) {
            $totalBytes += strlen($k) + 1 + strlen($v) + 1; // "KEY=VAL\0"
        }

        $mem->writeI32($countPtr, $count);
        $mem->writeI32($bufSizePtr, $totalBytes);

        return WasiErrno::SUCCESS;
    }

    public function environGet(Instance $inst, int $environPtr, int $environBufPtr): int
    {
        $mem = $inst->getMemory(0);
        if ($mem === null) {
            return WasiErrno::EFAULT;
        }

        $curBuf = $environBufPtr;
        $i = 0;
        foreach ($this->context->environ as $k => $v) {
            $entry = "{$k}={$v}\0";
            $mem->writeI32($environPtr + ($i * 4), $curBuf);
            $mem->writeBytes($curBuf, $entry);
            $curBuf += strlen($entry);
            $i++;
        }

        return WasiErrno::SUCCESS;
    }

    public function clockResGet(Instance $inst, int $clockId, int $resPtr): int
    {
        $mem = $inst->getMemory(0);
        if ($mem === null) {
            return WasiErrno::EFAULT;
        }

        // 1 nanosecond resolution
        $mem->writeI64($resPtr, 1);
        return WasiErrno::SUCCESS;
    }

    public function clockTimeGet(Instance $inst, int $clockId, int $precision, int $timePtr): int
    {
        $mem = $inst->getMemory(0);
        if ($mem === null) {
            return WasiErrno::EFAULT;
        }

        $nanos = match ($clockId) {
            WasiClockId::REALTIME => (int)(microtime(true) * 1_000_000_000),
            WasiClockId::MONOTONIC, WasiClockId::PROCESS_CPUTIME_ID, WasiClockId::THREAD_CPUTIME_ID => hrtime(true),
            default => -1,
        };

        if ($nanos === -1) {
            return WasiErrno::EINVAL;
        }

        $mem->writeI64($timePtr, $nanos);
        return WasiErrno::SUCCESS;
    }

    public function fdWrite(Instance $inst, int $fd, int $iovsPtr, int $iovsLen, int $nwrittenPtr): int
    {
        $mem = $inst->getMemory(0);
        if ($mem === null) {
            return WasiErrno::EFAULT;
        }

        $fileDesc = $this->context->getFd($fd);
        if ($fileDesc === null) {
            return WasiErrno::EBADF;
        }

        $totalWritten = 0;
        for ($i = 0; $i < $iovsLen; $i++) {
            $iovPtr = $iovsPtr + ($i * 8);
            $bufPtr = $mem->readU32($iovPtr);
            $bufLen = $mem->readU32($iovPtr + 4);

            if ($bufLen === 0) {
                continue;
            }

            $bytes = $mem->readBytes($bufPtr, $bufLen);
            $written = $fileDesc->write($bytes);
            $totalWritten += $written;

            if ($written < $bufLen) {
                break;
            }
        }

        $mem->writeI32($nwrittenPtr, $totalWritten);
        return WasiErrno::SUCCESS;
    }

    public function fdRead(Instance $inst, int $fd, int $iovsPtr, int $iovsLen, int $nreadPtr): int
    {
        $mem = $inst->getMemory(0);
        if ($mem === null) {
            return WasiErrno::EFAULT;
        }

        $fileDesc = $this->context->getFd($fd);
        if ($fileDesc === null) {
            return WasiErrno::EBADF;
        }

        $totalRead = 0;
        for ($i = 0; $i < $iovsLen; $i++) {
            $iovPtr = $iovsPtr + ($i * 8);
            $bufPtr = $mem->readU32($iovPtr);
            $bufLen = $mem->readU32($iovPtr + 4);

            if ($bufLen === 0) {
                continue;
            }

            $chunk = $fileDesc->read($bufLen);
            $readBytes = strlen($chunk);
            if ($readBytes > 0) {
                $mem->writeBytes($bufPtr, $chunk);
                $totalRead += $readBytes;
            }

            if ($readBytes < $bufLen) {
                break;
            }
        }

        $mem->writeI32($nreadPtr, $totalRead);
        return WasiErrno::SUCCESS;
    }

    public function fdSeek(Instance $inst, int $fd, int $offset, int $whence, int $newOffsetPtr): int
    {
        $mem = $inst->getMemory(0);
        if ($mem === null) {
            return WasiErrno::EFAULT;
        }

        $fileDesc = $this->context->getFd($fd);
        if ($fileDesc === null) {
            return WasiErrno::EBADF;
        }

        $phpWhence = match ($whence) {
            0 => SEEK_SET,
            1 => SEEK_CUR,
            2 => SEEK_END,
            default => -1,
        };

        if ($phpWhence === -1) {
            return WasiErrno::EINVAL;
        }

        $newPos = $fileDesc->seek($offset, $phpWhence);
        if ($newPos === -1) {
            return WasiErrno::EIO;
        }

        $mem->writeI64($newOffsetPtr, $newPos);
        return WasiErrno::SUCCESS;
    }

    public function fdClose(Instance $inst, int $fd): int
    {
        $closed = $this->context->closeFd($fd);
        return $closed ? WasiErrno::SUCCESS : WasiErrno::EBADF;
    }

    public function fdFdstatGet(Instance $inst, int $fd, int $statPtr): int
    {
        $mem = $inst->getMemory(0);
        if ($mem === null) {
            return WasiErrno::EFAULT;
        }

        $fileDesc = $this->context->getFd($fd);
        if ($fileDesc === null) {
            return WasiErrno::EBADF;
        }

        // wasi_fdstat_t:
        // u8 fs_filetype (2 = CHARACTER_DEVICE for stdio, 3 = DIRECTORY, 4 = REGULAR_FILE)
        // u16 fs_flags
        // u64 fs_rights_base
        // u64 fs_rights_inheriting
        $fileType = $fileDesc->isPreopen ? 3 : 2;
        $mem->writeI8($statPtr, $fileType);
        $mem->writeI16($statPtr + 2, 0); // flags
        $mem->writeI64($statPtr + 8, -1); // full rights
        $mem->writeI64($statPtr + 16, -1);

        return WasiErrno::SUCCESS;
    }

    public function fdPrestatGet(Instance $inst, int $fd, int $bufPtr): int
    {
        $mem = $inst->getMemory(0);
        if ($mem === null) {
            return WasiErrno::EFAULT;
        }

        $fileDesc = $this->context->getFd($fd);
        if ($fileDesc === null || !$fileDesc->isPreopen) {
            return WasiErrno::EBADF;
        }

        // wasi_prestat_t: u8 tag (0 = DIR), u32 name_len at offset 4
        $nameLen = strlen($fileDesc->preopenPath ?? '.');
        $mem->writeI8($bufPtr, 0); // PREOPENTYPE_DIR
        $mem->writeI32($bufPtr + 4, $nameLen);

        return WasiErrno::SUCCESS;
    }

    public function fdPrestatDirName(Instance $inst, int $fd, int $pathPtr, int $pathLen): int
    {
        $mem = $inst->getMemory(0);
        if ($mem === null) {
            return WasiErrno::EFAULT;
        }

        $fileDesc = $this->context->getFd($fd);
        if ($fileDesc === null || !$fileDesc->isPreopen) {
            return WasiErrno::EBADF;
        }

        $dirName = $fileDesc->preopenPath ?? '.';
        $mem->writeBytes($pathPtr, substr($dirName, 0, $pathLen));

        return WasiErrno::SUCCESS;
    }

    public function randomGet(Instance $inst, int $bufPtr, int $bufLen): int
    {
        $mem = $inst->getMemory(0);
        if ($mem === null) {
            return WasiErrno::EFAULT;
        }

        if ($bufLen > 0) {
            $bytes = random_bytes($bufLen);
            $mem->writeBytes($bufPtr, $bytes);
        }

        return WasiErrno::SUCCESS;
    }

    public function procExit(Instance $inst, int $rval): never
    {
        throw new WasiExitException($rval);
    }
}
