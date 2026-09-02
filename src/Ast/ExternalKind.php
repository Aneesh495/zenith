<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * WebAssembly external entity kind (function, table, memory, or global).
 */
enum ExternalKind: int
{
    case Function = 0x00;
    case Table = 0x01;
    case Memory = 0x02;
    case Global = 0x03;

    public function name(): string
    {
        return match ($this) {
            self::Function => 'func',
            self::Table => 'table',
            self::Memory => 'memory',
            self::Global => 'global',
        };
    }
}
