<?php

declare(strict_types=1);

namespace Zenith\Runtime;

use RuntimeException;

/**
 * Represents a WebAssembly runtime trap.
 */
class Trap extends RuntimeException
{
    public const UNREACHABLE = 'unreachable';
    public const INTEGER_OVERFLOW = 'integer overflow';
    public const INTEGER_DIVIDE_BY_ZERO = 'integer divide by zero';
    public const INVALID_CONVERSION_TO_INTEGER = 'invalid conversion to integer';
    public const OUT_OF_BOUNDS_MEMORY_ACCESS = 'out of bounds memory access';
    public const UNDEFINED_ELEMENT = 'undefined element';
    public const UNINITIALIZED_ELEMENT = 'uninitialized element';
    public const INDIRECT_CALL_TYPE_MISMATCH = 'indirect call type mismatch';
    public const CALL_STACK_EXHAUSTED = 'call stack exhausted';
    public const OUT_OF_BOUNDS_TABLE_ACCESS = 'out of bounds table access';

    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct('trap: ' . $message, $code, $previous);
    }

    public static function unreachable(): self
    {
        return new self(self::UNREACHABLE);
    }

    public static function integerOverflow(): self
    {
        return new self(self::INTEGER_OVERFLOW);
    }

    public static function divideByZero(): self
    {
        return new self(self::INTEGER_DIVIDE_BY_ZERO);
    }

    public static function invalidConversion(): self
    {
        return new self(self::INVALID_CONVERSION_TO_INTEGER);
    }

    public static function memoryOutOfBounds(int $offset, int $size, int $limit): self
    {
        return new self(sprintf(
            '%s (offset=%d, bytes=%d, limit=%d)',
            self::OUT_OF_BOUNDS_MEMORY_ACCESS,
            $offset,
            $size,
            $limit
        ));
    }

    public static function tableOutOfBounds(int $index, int $size): self
    {
        return new self(sprintf(
            '%s (index=%d, size=%d)',
            self::OUT_OF_BOUNDS_TABLE_ACCESS,
            $index,
            $size
        ));
    }

    public static function undefinedElement(int $index): self
    {
        return new self(sprintf('%s (index=%d)', self::UNDEFINED_ELEMENT, $index));
    }

    public static function uninitializedElement(int $index): self
    {
        return new self(sprintf('%s (index=%d)', self::UNINITIALIZED_ELEMENT, $index));
    }

    public static function typeMismatch(string $expected, string $actual): self
    {
        return new self(sprintf(
            '%s (expected %s, got %s)',
            self::INDIRECT_CALL_TYPE_MISMATCH,
            $expected,
            $actual
        ));
    }

    public static function callStackExhausted(int $depth): self
    {
        return new self(sprintf('%s (depth=%d)', self::CALL_STACK_EXHAUSTED, $depth));
    }
}
