<?php

declare(strict_types=1);

namespace Zenith\Text;

/**
 * WebAssembly Text format token types.
 */
enum TokenType: string
{
    case LParen = '(';
    case RParen = ')';
    case Keyword = 'keyword';
    case Identifier = 'identifier'; // $foo
    case Integer = 'integer';
    case Float = 'float';
    case String = 'string';
    case Eof = 'eof';
}
