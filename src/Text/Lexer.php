<?php

declare(strict_types=1);

namespace Zenith\Text;

use RuntimeException;

/**
 * Lexer for WebAssembly Text format (WAT).
 *
 * Supports nested block comments, string escapes, hex/float numeric formats,
 * identifiers, and keywords.
 */
final class Lexer
{
    private int $offset = 0;
    private int $length;
    private int $line = 1;
    private int $column = 1;

    public function __construct(
        private readonly string $source
    ) {
        $this->length = strlen($source);
    }

    /**
     * @return list<Token>
     */
    public function tokenize(): array
    {
        $tokens = [];

        while ($this->offset < $this->length) {
            $this->skipWhitespaceAndComments();

            if ($this->offset >= $this->length) {
                break;
            }

            $char = $this->source[$this->offset];
            $curLine = $this->line;
            $curCol = $this->column;

            if ($char === '(') {
                $tokens[] = new Token(TokenType::LParen, '(', $curLine, $curCol);
                $this->advance();
                continue;
            }

            if ($char === ')') {
                $tokens[] = new Token(TokenType::RParen, ')', $curLine, $curCol);
                $this->advance();
                continue;
            }

            if ($char === '"') {
                $tokens[] = $this->readString();
                continue;
            }

            if ($char === '$') {
                $tokens[] = $this->readIdentifier();
                continue;
            }

            $tokens[] = $this->readAtom();
        }

        $tokens[] = new Token(TokenType::Eof, '', $this->line, $this->column);
        return $tokens;
    }

    private function advance(): void
    {
        if ($this->offset < $this->length) {
            if ($this->source[$this->offset] === "\n") {
                $this->line++;
                $this->column = 1;
            } else {
                $this->column++;
            }
            $this->offset++;
        }
    }

    private function peek(int $offset = 0): ?string
    {
        $idx = $this->offset + $offset;
        return $idx < $this->length ? $this->source[$idx] : null;
    }

    private function skipWhitespaceAndComments(): void
    {
        while ($this->offset < $this->length) {
            $char = $this->source[$this->offset];

            if ($char === ' ' || $char === "\t" || $char === "\r" || $char === "\n") {
                $this->advance();
                continue;
            }

            // Line comment: ;;
            if ($char === ';' && $this->peek(1) === ';') {
                while ($this->offset < $this->length && $this->source[$this->offset] !== "\n") {
                    $this->advance();
                }
                continue;
            }

            // Block comment: (; ... ;) with support for nesting
            if ($char === '(' && $this->peek(1) === ';') {
                $depth = 1;
                $this->advance(); // skip (
                $this->advance(); // skip ;

                while ($this->offset < $this->length && $depth > 0) {
                    if ($this->source[$this->offset] === '(' && $this->peek(1) === ';') {
                        $depth++;
                        $this->advance();
                        $this->advance();
                    } elseif ($this->source[$this->offset] === ';' && $this->peek(1) === ')') {
                        $depth--;
                        $this->advance();
                        $this->advance();
                    } else {
                        $this->advance();
                    }
                }

                if ($depth > 0) {
                    throw new RuntimeException("Unclosed block comment starting at line {$this->line}");
                }
                continue;
            }

            break;
        }
    }

    private function readString(): Token
    {
        $startLine = $this->line;
        $startCol = $this->column;

        $this->advance(); // skip opening quote
        $result = '';

        while ($this->offset < $this->length) {
            $char = $this->source[$this->offset];

            if ($char === '"') {
                $this->advance(); // skip closing quote
                return new Token(TokenType::String, $result, $startLine, $startCol);
            }

            if ($char === '\\') {
                $this->advance();
                if ($this->offset >= $this->length) {
                    throw new RuntimeException("Unterminated string escape at line {$this->line}");
                }

                $esc = $this->source[$this->offset];
                match ($esc) {
                    'n' => $result .= "\n",
                    't' => $result .= "\t",
                    'r' => $result .= "\r",
                    '"' => $result .= '"',
                    '\'' => $result .= '\'',
                    '\\' => $result .= '\\',
                    default => (function () use (&$result, $esc) {
                        // Check hex byte escape \hh
                        if (ctype_xdigit($esc) && $this->peek(1) !== null && ctype_xdigit($this->peek(1))) {
                            $hex = $esc . $this->peek(1);
                            $this->advance();
                            $result .= chr((int)hexdec($hex));
                        } else {
                            $result .= $esc;
                        }
                    })(),
                };
                $this->advance();
            } else {
                $result .= $char;
                $this->advance();
            }
        }

        throw new RuntimeException("Unterminated string starting at line {$startLine}");
    }

    private function readIdentifier(): Token
    {
        $startLine = $this->line;
        $startCol = $this->column;
        $val = '$';
        $this->advance(); // skip $

        while ($this->offset < $this->length) {
            $char = $this->source[$this->offset];
            if ($this->isIdChar($char)) {
                $val .= $char;
                $this->advance();
            } else {
                break;
            }
        }

        return new Token(TokenType::Identifier, $val, $startLine, $startCol);
    }

    private function readAtom(): Token
    {
        $startLine = $this->line;
        $startCol = $this->column;
        $atom = '';

        while ($this->offset < $this->length) {
            $char = $this->source[$this->offset];
            if ($char === '(' || $char === ')' || $char === '"' || $char === ';' ||
                $char === ' ' || $char === "\t" || $char === "\r" || $char === "\n") {
                break;
            }
            $atom .= $char;
            $this->advance();
        }

        $clean = str_replace('_', '', $atom);

        // Check if integer (hex or decimal)
        if (preg_match('/^[+-]?(?:0x[0-9a-fA-F]+|[0-9]+)$/', $clean)) {
            return new Token(TokenType::Integer, $atom, $startLine, $startCol);
        }

        // Check if float (including nan, inf)
        if (preg_match('/^[+-]?(?:nan(?::0x[0-9a-fA-F]+)?|inf|[0-9]+\.[0-9]*(?:[eE][+-]?[0-9]+)?|[0-9]+[eE][+-]?[0-9]+|0x[0-9a-fA-F]+\.[0-9a-fA-F]*(?:[pP][+-]?[0-9]+)?)$/i', $clean)) {
            return new Token(TokenType::Float, $atom, $startLine, $startCol);
        }

        // Otherwise keyword / mnemonic
        return new Token(TokenType::Keyword, $atom, $startLine, $startCol);
    }

    private function isIdChar(string $c): bool
    {
        $o = ord($c);
        return ($o >= 97 && $o <= 122) // a-z
            || ($o >= 65 && $o <= 90)  // A-Z
            || ($o >= 48 && $o <= 57)  // 0-9
            || in_array($c, ['!', '#', '$', '%', '&', '\'', '*', '+', '-', '.', '/', ':', '<', '=', '>', '?', '@', '\\', '^', '_', '`', '|', '~'], true);
    }
}
