<?php

declare(strict_types=1);

namespace Zenith\Binary;

/**
 * WebAssembly binary section identifiers.
 */
enum SectionType: int
{
    case Custom = 0;
    case Type = 1;
    case Import = 2;
    case Function = 3;
    case Table = 4;
    case Memory = 5;
    case Global = 6;
    case Export = 7;
    case Start = 8;
    case Element = 9;
    case Code = 10;
    case Data = 11;
    case DataCount = 12;

    public function name(): string
    {
        return match ($this) {
            self::Custom => 'custom',
            self::Type => 'type',
            self::Import => 'import',
            self::Function => 'function',
            self::Table => 'table',
            self::Memory => 'memory',
            self::Global => 'global',
            self::Export => 'export',
            self::Start => 'start',
            self::Element => 'element',
            self::Code => 'code',
            self::Data => 'data',
            self::DataCount => 'datacount',
        };
    }
}
