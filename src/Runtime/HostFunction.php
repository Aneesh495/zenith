<?php

declare(strict_types=1);

namespace Zenith\Runtime;

use Closure;
use Zenith\Ast\FuncType;

/**
 * Host-provided native function callable from WebAssembly bytecode.
 */
final class HostFunction
{
    /**
     * @param FuncType $type Expected function type signature
     * @param Closure $callback Callable with signature: function(Instance $caller, mixed ...$args): mixed
     * @param string|null $name Optional debug name
     */
    public function __construct(
        public readonly FuncType $type,
        public readonly Closure $callback,
        public readonly ?string $name = null,
    ) {
    }

    /**
     * @param Instance $caller
     * @param list<Value> $args
     * @return list<Value>
     */
    public function invoke(Instance $caller, array $args): array
    {
        $rawArgs = array_map(static fn(Value $v): mixed => $v->raw, $args);
        $result = ($this->callback)($caller, ...$rawArgs);

        if (empty($this->type->results)) {
            return [];
        }

        if (count($this->type->results) === 1) {
            $resType = $this->type->results[0];
            return [match ($resType) {
                \Zenith\Ast\ValueType::I32 => Value::i32((int)$result),
                \Zenith\Ast\ValueType::I64 => Value::i64((int)$result),
                \Zenith\Ast\ValueType::F32 => Value::f32((float)$result),
                \Zenith\Ast\ValueType::F64 => Value::f64((float)$result),
                \Zenith\Ast\ValueType::FuncRef => Value::funcRef($result !== null ? (int)$result : null),
                \Zenith\Ast\ValueType::ExternRef => Value::externRef($result),
                default => Value::i32(0),
            }];
        }

        if (is_array($result)) {
            $out = [];
            foreach ($this->type->results as $i => $resType) {
                $item = $result[$i] ?? 0;
                $out[] = match ($resType) {
                    \Zenith\Ast\ValueType::I32 => Value::i32((int)$item),
                    \Zenith\Ast\ValueType::I64 => Value::i64((int)$item),
                    \Zenith\Ast\ValueType::F32 => Value::f32((float)$item),
                    \Zenith\Ast\ValueType::F64 => Value::f64((float)$item),
                    default => Value::i32(0),
                };
            }
            return $out;
        }

        return [];
    }
}
