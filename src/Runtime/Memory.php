<?php

declare(strict_types=1);

namespace Zenith\Runtime;

use Zenith\Ast\Limits;
use Zenith\Ast\MemoryType;

/**
 * WebAssembly linear memory manager.
 *
 * Implements 64KiB page allocation, dynamic expansion (memory.grow),
 * bounds checking, little-endian binary load/store routines, and bulk memory operations.
 */
final class Memory
{
    public const PAGE_SIZE = 65536; // 64 KiB
    public const MAX_PAGES = 65536; // 4 GiB theoretical limit

    private string $buffer;
    private int $pages;
    private ?int $maxPages;

    public function __construct(MemoryType $type)
    {
        $this->pages = $type->limits->min;
        $this->maxPages = $type->limits->max;
        $this->buffer = str_repeat("\0", $this->pages * self::PAGE_SIZE);
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    public function size(): int
    {
        return $this->pages;
    }

    public function pages(): int
    {
        return $this->pages;
    }

    public function getByteLength(): int
    {
        return strlen($this->buffer);
    }

    public function sizeBytes(): int
    {
        return strlen($this->buffer);
    }

    /**
     * Grow linear memory by a specified number of 64KiB pages.
     *
     * @param int $deltaPages Number of additional pages to allocate
     * @return int Previous page count, or -1 if growth failed
     */
    public function grow(int $deltaPages): int
    {
        if ($deltaPages < 0) {
            return -1;
        }

        if ($deltaPages === 0) {
            return $this->pages;
        }

        $newPages = $this->pages + $deltaPages;

        if ($newPages > self::MAX_PAGES) {
            return -1;
        }

        if ($this->maxPages !== null && $newPages > $this->maxPages) {
            return -1;
        }

        $oldPages = $this->pages;
        $this->buffer .= str_repeat("\0", $deltaPages * self::PAGE_SIZE);
        $this->pages = $newPages;

        return $oldPages;
    }

    private function checkBounds(int $offset, int $size): void
    {
        $bufLen = strlen($this->buffer);
        if ($offset < 0 || $size < 0 || $offset + $size > $bufLen) {
            throw Trap::memoryOutOfBounds($offset, $size, $bufLen);
        }
    }

    public function readBytes(int $offset, int $length): string
    {
        $this->checkBounds($offset, $length);
        return substr($this->buffer, $offset, $length);
    }

    public function writeBytes(int $offset, string $data): void
    {
        $length = strlen($data);
        $this->checkBounds($offset, $length);

        for ($i = 0; $i < $length; $i++) {
            $this->buffer[$offset + $i] = $data[$i];
        }
    }

    public function readU8(int $offset): int
    {
        $this->checkBounds($offset, 1);
        return ord($this->buffer[$offset]);
    }

    public function readI8(int $offset): int
    {
        $this->checkBounds($offset, 1);
        return unpack('c', $this->buffer[$offset])[1];
    }

    public function writeI8(int $offset, int $val): void
    {
        $this->checkBounds($offset, 1);
        $this->buffer[$offset] = chr($val & 0xFF);
    }

    public function readU16(int $offset): int
    {
        $this->checkBounds($offset, 2);
        return unpack('v', substr($this->buffer, $offset, 2))[1];
    }

    public function readI16(int $offset): int
    {
        $this->checkBounds($offset, 2);
        return unpack('s', substr($this->buffer, $offset, 2))[1];
    }

    public function writeI16(int $offset, int $val): void
    {
        $this->checkBounds($offset, 2);
        $bytes = pack('v', $val & 0xFFFF);
        $this->buffer[$offset] = $bytes[0];
        $this->buffer[$offset + 1] = $bytes[1];
    }

    public function readU32(int $offset): int
    {
        $this->checkBounds($offset, 4);
        return unpack('V', substr($this->buffer, $offset, 4))[1];
    }

    public function readI32(int $offset): int
    {
        $this->checkBounds($offset, 4);
        return unpack('l', substr($this->buffer, $offset, 4))[1];
    }

    public function writeI32(int $offset, int $val): void
    {
        $this->checkBounds($offset, 4);
        $bytes = pack('V', $val & 0xFFFFFFFF);
        for ($i = 0; $i < 4; $i++) {
            $this->buffer[$offset + $i] = $bytes[$i];
        }
    }

    public function readI64(int $offset): int
    {
        $this->checkBounds($offset, 8);
        return unpack('P', substr($this->buffer, $offset, 8))[1];
    }

    public function writeI64(int $offset, int $val): void
    {
        $this->checkBounds($offset, 8);
        $bytes = pack('P', $val);
        for ($i = 0; $i < 8; $i++) {
            $this->buffer[$offset + $i] = $bytes[$i];
        }
    }

    public function readF32(int $offset): float
    {
        $this->checkBounds($offset, 4);
        return unpack('f', substr($this->buffer, $offset, 4))[1];
    }

    public function writeF32(int $offset, float $val): void
    {
        $this->checkBounds($offset, 4);
        $bytes = pack('f', $val);
        for ($i = 0; $i < 4; $i++) {
            $this->buffer[$offset + $i] = $bytes[$i];
        }
    }

    public function readF64(int $offset): float
    {
        $this->checkBounds($offset, 8);
        return unpack('d', substr($this->buffer, $offset, 8))[1];
    }

    public function writeF64(int $offset, float $val): void
    {
        $this->checkBounds($offset, 8);
        $bytes = pack('d', $val);
        for ($i = 0; $i < 8; $i++) {
            $this->buffer[$offset + $i] = $bytes[$i];
        }
    }

    public function copy(int $dst, int $src, int $size): void
    {
        if ($size === 0) {
            return;
        }

        $this->checkBounds($dst, $size);
        $this->checkBounds($src, $size);

        $chunk = substr($this->buffer, $src, $size);
        $this->writeBytes($dst, $chunk);
    }

    public function fill(int $dst, int $val, int $size): void
    {
        if ($size === 0) {
            return;
        }

        $this->checkBounds($dst, $size);
        $chunk = str_repeat(chr($val & 0xFF), $size);
        $this->writeBytes($dst, $chunk);
    }
}
