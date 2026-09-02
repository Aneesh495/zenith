<?php

declare(strict_types=1);

namespace Zenith\Runtime;

use Zenith\Ast\TableType;
use Zenith\Ast\ValueType;

/**
 * WebAssembly function and reference table.
 *
 * Implements reference storage, bounds checking, dynamic table growth,
 * and indirect dispatch element lookup.
 */
final class Table
{
    /** @var list<mixed> */
    private array $elements;
    private int $size;
    private ?int $maxSize;
    public readonly ValueType $elemType;

    public function __construct(TableType $type)
    {
        $this->elemType = $type->elemType;
        $this->size = $type->limits->min;
        $this->maxSize = $type->limits->max;
        $this->elements = array_fill(0, $this->size, null);
    }

    public function size(): int
    {
        return $this->size;
    }

    public function get(int $index): mixed
    {
        if ($index < 0 || $index >= $this->size) {
            throw Trap::tableOutOfBounds($index, $this->size);
        }
        return $this->elements[$index];
    }

    public function set(int $index, mixed $value): void
    {
        if ($index < 0 || $index >= $this->size) {
            throw Trap::tableOutOfBounds($index, $this->size);
        }
        $this->elements[$index] = $value;
    }

    public function grow(int $delta, mixed $initVal = null): int
    {
        if ($delta < 0) {
            return -1;
        }

        if ($delta === 0) {
            return $this->size;
        }

        $newSize = $this->size + $delta;
        if ($this->maxSize !== null && $newSize > $this->maxSize) {
            return -1;
        }

        $oldSize = $this->size;
        for ($i = 0; $i < $delta; $i++) {
            $this->elements[] = $initVal;
        }
        $this->size = $newSize;

        return $oldSize;
    }

    public function fill(int $dst, mixed $val, int $count): void
    {
        if ($count === 0) {
            return;
        }

        if ($dst < 0 || $count < 0 || $dst + $count > $this->size) {
            throw Trap::tableOutOfBounds($dst, $this->size);
        }

        for ($i = 0; $i < $count; $i++) {
            $this->elements[$dst + $i] = $val;
        }
    }

    public function copy(int $dst, int $src, int $count): void
    {
        if ($count === 0) {
            return;
        }

        if ($dst < 0 || $src < 0 || $count < 0 || $dst + $count > $this->size || $src + $count > $this->size) {
            throw Trap::tableOutOfBounds(max($dst, $src), $this->size);
        }

        $slice = array_slice($this->elements, $src, $count);
        for ($i = 0; $i < $count; $i++) {
            $this->elements[$dst + $i] = $slice[$i];
        }
    }
}
