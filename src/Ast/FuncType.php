<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * WebAssembly function type signature (parameters and results).
 */
final class FuncType
{
    /**
     * @param list<ValueType> $params
     * @param list<ValueType> $results
     */
    public function __construct(
        public readonly array $params = [],
        public readonly array $results = [],
    ) {
    }

    public function equals(self $other): bool
    {
        if (count($this->params) !== count($other->params)) {
            return false;
        }

        if (count($this->results) !== count($other->results)) {
            return false;
        }

        foreach ($this->params as $i => $param) {
            if ($param !== $other->params[$i]) {
                return false;
            }
        }

        foreach ($this->results as $i => $result) {
            if ($result !== $other->results[$i]) {
                return false;
            }
        }

        return true;
    }

    public function __toString(): string
    {
        $parts = [];
        if (!empty($this->params)) {
            $paramNames = array_map(static fn(ValueType $t): string => $t->name(), $this->params);
            $parts[] = '(param ' . implode(' ', $paramNames) . ')';
        }
        if (!empty($this->results)) {
            $resultNames = array_map(static fn(ValueType $t): string => $t->name(), $this->results);
            $parts[] = '(result ' . implode(' ', $resultNames) . ')';
        }
        return implode(' ', $parts);
    }
}
