<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * WebAssembly module export declaration.
 */
final class Export
{
    /**
     * @param string $name Export identifier name
     * @param ExternalKind $kind Kind of exported entity (function, table, memory, global)
     * @param int $index Index in the corresponding module index space
     */
    public function __construct(
        public readonly string $name,
        public readonly ExternalKind $kind,
        public readonly int $index,
    ) {
    }

    public function isFunction(): bool
    {
        return $this->kind === ExternalKind::Function;
    }

    public function isMemory(): bool
    {
        return $this->kind === ExternalKind::Memory;
    }

    public function isTable(): bool
    {
        return $this->kind === ExternalKind::Table;
    }

    public function isGlobal(): bool
    {
        return $this->kind === ExternalKind::Global;
    }
}
