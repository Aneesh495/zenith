<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * WebAssembly module import declaration.
 */
final class Import
{
    /**
     * @param string $module Namespace module name (e.g. "wasi_snapshot_preview1", "env")
     * @param string $field Exported field name in the imported module (e.g. "fd_write")
     * @param ExternalKind $kind Kind of imported external entity
     * @param mixed $desc Descriptor (type index for func, TableType, MemoryType, GlobalType)
     */
    public function __construct(
        public readonly string $module,
        public readonly string $field,
        public readonly ExternalKind $kind,
        public readonly mixed $desc,
    ) {
    }

    public function isFunction(): bool
    {
        return $this->kind === ExternalKind::Function;
    }

    public function getTypeIndex(): int
    {
        assert($this->isFunction());
        return (int)$this->desc;
    }
}
