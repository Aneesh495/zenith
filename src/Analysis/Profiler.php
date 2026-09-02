<?php

declare(strict_types=1);

namespace Zenith\Analysis;

use Zenith\Ast\Instruction;
use Zenith\Binary\Opcode;
use Zenith\Runtime\Frame;
use Zenith\Runtime\Stack;
use Zenith\Runtime\Vm;

/**
 * Execution profiler and telemetry monitor for Zenith VM.
 */
final class Profiler
{
    private int $totalInstructions = 0;
    /** @var array<string, int> */
    private array $opcodeCounts = [];
    /** @var array<string, int> */
    private array $funcCalls = [];
    private int $peakStackDepth = 0;
    private int $startNanos = 0;
    private int $endNanos = 0;

    public function attach(Vm $vm): void
    {
        $this->startNanos = hrtime(true);
        $vm->setTracing(true, function (Frame $frame, Instruction $ins, Stack $stack): void {
            $this->totalInstructions++;
            $m = $ins->getMnemonic();
            $this->opcodeCounts[$m] = ($this->opcodeCounts[$m] ?? 0) + 1;

            if ($ins->is(Opcode::CALL)) {
                $target = (string)$ins->immediate;
                $this->funcCalls[$target] = ($this->funcCalls[$target] ?? 0) + 1;
            }

            $depth = $stack->height();
            if ($depth > $this->peakStackDepth) {
                $this->peakStackDepth = $depth;
            }
        });
    }

    public function detach(Vm $vm): void
    {
        $this->endNanos = hrtime(true);
        $vm->setTracing(false, null);
    }

    public function getTotalInstructions(): int
    {
        return $this->totalInstructions;
    }

    public function getElapsedNanos(): int
    {
        return max(1, $this->endNanos - $this->startNanos);
    }

    public function getElapsedSeconds(): float
    {
        return $this->getElapsedNanos() / 1_000_000_000.0;
    }

    public function getThroughput(): float
    {
        $sec = $this->getElapsedSeconds();
        return $sec > 0 ? $this->totalInstructions / $sec : 0.0;
    }

    public function renderReport(): string
    {
        arsort($this->opcodeCounts);

        $lines = [];
        $lines[] = '================ Zenith Execution Profile ================';
        $lines[] = sprintf('Total Instructions Executed : %s', number_format($this->totalInstructions));
        $lines[] = sprintf('Elapsed Time                : %.3f ms', $this->getElapsedNanos() / 1_000_000.0);
        $lines[] = sprintf('Execution Throughput        : %s opcodes/sec', number_format((int)$this->getThroughput()));
        $lines[] = sprintf('Peak Operand Stack Height   : %d elements', $this->peakStackDepth);
        $lines[] = '';
        $lines[] = 'Top Instructions by Frequency:';
        $lines[] = sprintf('  %-20s | %-12s | %-8s', 'Opcode', 'Executions', 'Share');
        $lines[] = '  ' . str_repeat('-', 46);

        $limit = 15;
        $shown = 0;
        foreach ($this->opcodeCounts as $m => $count) {
            $share = $this->totalInstructions > 0 ? ($count / $this->totalInstructions) * 100.0 : 0.0;
            $lines[] = sprintf('  %-20s | %-12s | %6.2f%%', $m, number_format($count), $share);
            $shown++;
            if ($shown >= $limit) {
                break;
            }
        }

        $lines[] = '==========================================================';
        return implode("\n", $lines) . "\n";
    }
}
