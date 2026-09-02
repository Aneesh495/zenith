<?php

declare(strict_types=1);

namespace Zenith\Text;

/**
 * Representation of a lexical token in WebAssembly Text format.
 */
final class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
        public readonly int $line,
        public readonly int $column,
    ) {
    }

    public function is(TokenType $type, ?string $value = null): bool
    {
        if ($this->type !== $type) {
            return false;
        }
        return $value === null || $this->value === $value;
    }

    public function isKeyword(?string $value = null): bool
    {
        return $this->is(TokenType::Keyword, $value);
    }
}
