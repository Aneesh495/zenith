<?php

declare(strict_types=1);

namespace Zenith\Debugger;

use Zenith\Ast\Instruction;
use Zenith\Runtime\Frame;
use Zenith\Runtime\Memory;
use Zenith\Runtime\Stack;
use Zenith\Runtime\Value;

/**
 * Terminal UI renderer for the Zenith step debugger.
 */
final class TuiRenderer
{
    public static function render(Frame $frame, Instruction $currentIns, Stack $stack, ?Memory $memory = null, int $memOffset = 0): string
    {
        $lines = [];
        $width = 78;

        // Header bar
        $funcName = $frame->func->debugName !== null ? "\${$frame->func->debugName}" : "(anonymous)";
        $headerText = sprintf(' Zenith Debugger | Func: %s | IP: %04d | Stack: %d ', $funcName, $frame->ip, $stack->height());
        $lines[] = Terminal::color(Terminal::horizontalRule($width, '='), Terminal::CYAN);
        $lines[] = Terminal::color($headerText, Terminal::BOLD . Terminal::BRIGHT_CYAN);
        $lines[] = Terminal::color(Terminal::horizontalRule($width, '-'), Terminal::CYAN);

        // Disassembly context
        $lines[] = Terminal::color(' Bytecode Disassembly:', Terminal::BOLD . Terminal::WHITE);
        $body = $frame->func->body;
        $curIdx = $frame->ip - 1; // current instruction index
        $startIdx = max(0, $curIdx - 4);
        $endIdx = min(count($body) - 1, $curIdx + 4);

        for ($k = $startIdx; $k <= $endIdx; $k++) {
            $ins = $body[$k];
            $isCur = $k === $curIdx;
            $marker = $isCur ? Terminal::color('=> ', Terminal::BRIGHT_GREEN . Terminal::BOLD) : '   ';
            $offsetStr = sprintf('[%04d] ', $k);
            $text = (string)$ins;

            if ($isCur) {
                $lines[] = $marker . Terminal::color($offsetStr . $text, Terminal::BRIGHT_GREEN . Terminal::BOLD);
            } else {
                $lines[] = $marker . Terminal::color($offsetStr . $text, Terminal::DIM . Terminal::WHITE);
            }
        }
        $lines[] = '';

        // Operand Stack & Locals side-by-side
        $lines[] = Terminal::color(' Operand Stack (top to bottom):', Terminal::BOLD . Terminal::WHITE);
        $stackItems = array_reverse($stack->toArray());
        if (empty($stackItems)) {
            $lines[] = Terminal::color('   <empty>', Terminal::DIM);
        } else {
            foreach (array_slice($stackItems, 0, 6) as $sIdx => $val) {
                $lines[] = sprintf('   [%d] %s', $sIdx, Terminal::color((string)$val, Terminal::YELLOW));
            }
            if (count($stackItems) > 6) {
                $lines[] = sprintf('   ... (%d more items)', count($stackItems) - 6);
            }
        }
        $lines[] = '';

        // Locals
        $lines[] = Terminal::color(' Local Variables:', Terminal::BOLD . Terminal::WHITE);
        if (empty($frame->locals)) {
            $lines[] = Terminal::color('   <none>', Terminal::DIM);
        } else {
            foreach ($frame->locals as $lIdx => $val) {
                $lName = $frame->func->localNames[$lIdx] ?? "\$l{$lIdx}";
                $lines[] = sprintf('   %-10s = %s', $lName, Terminal::color((string)$val, Terminal::BRIGHT_YELLOW));
            }
        }
        $lines[] = '';

        // Memory Hex Dump (if memory exists)
        if ($memory !== null && $memory->getByteLength() > 0) {
            $lines[] = Terminal::color(sprintf(' Linear Memory [offset 0x%04X]:', $memOffset), Terminal::BOLD . Terminal::WHITE);
            $bytesToDump = min(32, $memory->getByteLength() - $memOffset);
            if ($bytesToDump > 0) {
                $hexLines = self::hexDump($memory->readBytes($memOffset, $bytesToDump), $memOffset);
                foreach ($hexLines as $hl) {
                    $lines[] = '   ' . $hl;
                }
            }
            $lines[] = '';
        }

        $lines[] = Terminal::color(Terminal::horizontalRule($width, '-'), Terminal::CYAN);
        $lines[] = Terminal::color(' Commands: [s]tep | [c]ontinue | [m]em <addr> | [q]uit', Terminal::BRIGHT_WHITE);

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return list<string>
     */
    public static function hexDump(string $data, int $baseOffset = 0): array
    {
        $lines = [];
        $len = strlen($data);

        for ($i = 0; $i < $len; $i += 16) {
            $chunk = substr($data, $i, 16);
            $chunkLen = strlen($chunk);

            $hexParts = [];
            for ($k = 0; $k < 16; $k++) {
                if ($k < $chunkLen) {
                    $hexParts[] = sprintf('%02X', ord($chunk[$k]));
                } else {
                    $hexParts[] = '  ';
                }
                if ($k === 7) {
                    $hexParts[] = ' ';
                }
            }

            $ascii = '';
            for ($k = 0; $k < $chunkLen; $k++) {
                $o = ord($chunk[$k]);
                $ascii .= ($o >= 32 && $o <= 126) ? $chunk[$k] : '.';
            }

            $lines[] = sprintf('%04X:  %s  |%s|',
                $baseOffset + $i,
                implode(' ', $hexParts),
                $ascii
            );
        }

        return $lines;
    }
}
