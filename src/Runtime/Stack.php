<?php

declare(strict_types=1);

namespace Zenith\Runtime;

use RuntimeException;

/**
 * Fast operand evaluation stack for the WebAssembly interpreter.
 */
final class Stack
{
    /** @var list<Value> */
    private array $values = [];

    public function push(Value $val): void
    {
        $this->values[] = $val;
    }

    public function pop(): Value
    {
        if (empty($this->values)) {
            throw new RuntimeException('Operand stack underflow');
        }
        return array_pop($this->values);
    }

    public function popI32(): int
    {
        return $this->pop()->asI32();
    }

    public function popI64(): int
    {
        return $this->pop()->asI64();
    }

    public function popF32(): float
    {
        return $this->pop()->asF32();
    }

    public function popF64(): float
    {
        return $this->pop()->asF64();
    }

    public function peek(int $depth = 0): Value
    {
        $idx = count($this->values) - 1 - $depth;
        if ($idx < 0 || $idx >= count($this->values)) {
            throw new RuntimeException("Stack peek depth {$depth} out of bounds");
        }
        return $this->values[$idx];
    }

    public function height(): int
    {
        return count($this->values);
    }

    public function clear(): void
    {
        $this->values = [];
    }

    /**
     * Unwind stack to target height, preserving the topmost $keepCount elements.
     */
    public function unwind(int $targetHeight, int $keepCount = 0): void
    {
        if ($keepCount === 0) {
            $this->values = array_slice($this->values, 0, $targetHeight);
            return;
        }

        $top = array_slice($this->values, -$keepCount);
        $base = array_slice($this->values, 0, $targetHeight);
        $this->values = array_merge($base, $top);
    }

    /**
     * @return list<Value>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
