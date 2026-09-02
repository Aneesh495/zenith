<?php

declare(strict_types=1);

namespace Zenith\Runtime;

use RuntimeException;
use Zenith\Ast\GlobalType;

/**
 * Runtime instance of a WebAssembly global variable.
 */
final class GlobalInstance
{
    public function __construct(
        public readonly GlobalType $type,
        private Value $value,
    ) {
    }

    public function get(): Value
    {
        return $this->value;
    }

    public function set(Value $value): void
    {
        if (!$this->type->mutable) {
            throw new RuntimeException('Cannot mutate immutable global');
        }

        if ($value->type !== $this->type->valType) {
            throw new RuntimeException(sprintf(
                'Global type mismatch: expected %s, got %s',
                $this->type->valType->name(),
                $value->type->name()
            ));
        }

        $this->value = $value;
    }
}
