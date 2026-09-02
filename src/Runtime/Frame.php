<?php

declare(strict_types=1);

namespace Zenith\Runtime;

use RuntimeException;
use Zenith\Ast\FunctionDef;

/**
 * Call frame representing an active function activation on the call stack.
 */
final class Frame
{
    /** @var list<Label> */
    public array $labels = [];
    public int $ip = 0;

    /**
     * @param Instance $instance Bound module instance
     * @param FunctionDef $func Function definition containing bytecode
     * @param list<Value> $locals Parameter and local variable values
     * @param int $returnArity Expected number of results produced by this call
     */
    public function __construct(
        public readonly Instance $instance,
        public readonly FunctionDef $func,
        public array $locals,
        public readonly int $returnArity = 0,
    ) {
    }

    public function pushLabel(Label $label): void
    {
        $this->labels[] = $label;
    }

    public function popLabel(): Label
    {
        if (empty($this->labels)) {
            throw new RuntimeException('Label stack underflow');
        }
        return array_pop($this->labels);
    }

    public function getLabel(int $depth): Label
    {
        $idx = count($this->labels) - 1 - $depth;
        if ($idx < 0 || $idx >= count($this->labels)) {
            throw new RuntimeException("Invalid label depth: {$depth}");
        }
        return $this->labels[$idx];
    }
}
