<?php

declare(strict_types=1);

namespace Zenith\Debugger;

use Zenith\Ast\Instruction;
use Zenith\Runtime\Frame;
use Zenith\Runtime\Stack;
use Zenith\Runtime\Vm;

/**
 * Interactive debugger step controller.
 */
final class Stepper
{
    /** @var list<Breakpoint> */
    private array $breakpoints = [];
    private bool $singleStep = true;
    private int $memOffset = 0;

    public function addBreakpoint(Breakpoint $bp): void
    {
        $this->breakpoints[] = $bp;
    }

    public function attach(Vm $vm): void
    {
        $vm->setTracing(true, function (Frame $frame, Instruction $ins, Stack $stack): void {
            $hitBreakpoint = false;
            foreach ($this->breakpoints as $bp) {
                if ($bp->matches($frame, $ins, $stack)) {
                    $hitBreakpoint = true;
                    $this->singleStep = true;
                    break;
                }
            }

            if ($this->singleStep || $hitBreakpoint) {
                $this->interact($frame, $ins, $stack);
            }
        });
    }

    private function interact(Frame $frame, Instruction $ins, Stack $stack): void
    {
        $mem = $frame->instance->getMemory(0);

        echo Terminal::clearScreen();
        echo TuiRenderer::render($frame, $ins, $stack, $mem, $this->memOffset);

        while (true) {
            echo 'zenith-dbg> ';
            $line = trim((string)fgets(STDIN));

            if ($line === '' || $line === 's' || $line === 'step') {
                $this->singleStep = true;
                break;
            }

            if ($line === 'c' || $line === 'continue') {
                $this->singleStep = false;
                break;
            }

            if (str_starts_with($line, 'm ') || str_starts_with($line, 'mem ')) {
                $parts = explode(' ', $line);
                $rawAddr = $parts[1] ?? '0';
                $this->memOffset = str_starts_with($rawAddr, '0x') ? (int)hexdec($rawAddr) : (int)$rawAddr;
                echo Terminal::clearScreen();
                echo TuiRenderer::render($frame, $ins, $stack, $mem, $this->memOffset);
                continue;
            }

            if ($line === 'q' || $line === 'quit' || $line === 'exit') {
                echo "Debugger detached.\n";
                exit(0);
            }

            echo "Unknown command: {$line}\n";
        }
    }
}
