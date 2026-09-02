<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * Representation of memory or table limits (minimum and optional maximum).
 */
final class Limits
{
    public function __construct(
        public readonly int $min,
        public readonly ?int $max = null,
    ) {
    }

    public function satisfies(self $expected): bool
    {
        if ($this->min < $expected->min) {
            return false;
        }

        if ($expected->max !== null) {
            if ($this->max === null || $this->max > $expected->max) {
                return false;
            }
        }

        return true;
    }
}
