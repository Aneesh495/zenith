<?php

declare(strict_types=1);

namespace Zenith\Debugger;

use Closure;
use Zenith\Ast\Instruction;
use Zenith\Runtime\Frame;
use Zenith\Runtime\Stack;

/**
 * Breakpoint condition and matcher for the interactive step debugger.
 */
final class Breakpoint
{
    /**
     * @param string|int $target Function name, function index, or instruction IP
     * @param Closure|null $condition Optional boolean closure: function(Frame, Instruction, Stack): bool
     * @param bool $enabled Whether breakpoint is active
     */
    public function __construct(
        public readonly string|int $target,
        public readonly ?Closure $condition = null,
        public bool $enabled = true,
    ) {
    }

    public function matches(Frame $frame, Instruction $ins, Stack $stack): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $matched = false;

        if (is_int($this->target)) {
            if ($frame->ip === $this->target) {
                $matched = true;
            }
        } elseif (is_string($this->target)) {
            if ($frame->func->debugName === $this->target) {
                $matched = true;
            }
        }

        if ($matched && $this->condition !== null) {
            return (bool)($this->condition)($frame, $ins, $stack);
        }

        return $matched;
    }
}
