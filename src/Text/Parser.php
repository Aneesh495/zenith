<?php

declare(strict_types=1);

namespace Zenith\Text;

use RuntimeException;

/**
 * S-expression parser for WebAssembly Text format.
 */
final class Parser
{
    private int $offset = 0;
    private int $count;

    /**
     * @param list<Token> $tokens
     */
    public function __construct(
        private readonly array $tokens
    ) {
        $this->count = count($tokens);
    }

    /**
     * Parse token stream into S-expression nested arrays.
     *
     * @return list<mixed>
     */
    public function parse(): array
    {
        $nodes = [];
        while (!$this->isEof()) {
            if ($this->peek()->type === TokenType::RParen) {
                throw new RuntimeException("Unexpected closing parenthesis at line {$this->peek()->line}");
            }
            $nodes[] = $this->parseNode();
        }
        return $nodes;
    }

    public function parseNode(): mixed
    {
        $tok = $this->peek();

        if ($tok->type === TokenType::LParen) {
            $this->advance(); // skip '('
            $list = [];

            while (!$this->isEof() && $this->peek()->type !== TokenType::RParen) {
                $list[] = $this->parseNode();
            }

            if ($this->isEof()) {
                throw new RuntimeException("Unmatched opening parenthesis at line {$tok->line}");
            }

            $this->advance(); // skip ')'
            return $list;
        }

        $this->advance();
        return $tok;
    }

    private function peek(): Token
    {
        return $this->tokens[$this->offset];
    }

    private function advance(): Token
    {
        $tok = $this->tokens[$this->offset];
        if ($this->offset < $this->count - 1) {
            $this->offset++;
        }
        return $tok;
    }

    private function isEof(): bool
    {
        return $this->tokens[$this->offset]->type === TokenType::Eof;
    }
}
