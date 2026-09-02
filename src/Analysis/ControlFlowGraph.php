<?php

declare(strict_types=1);

namespace Zenith\Analysis;

use Zenith\Ast\FunctionDef;
use Zenith\Binary\Opcode;

/**
 * Control flow graph generator and topological analysis engine for Wasm bytecode.
 */
final class ControlFlowGraph
{
    /** @var array<int, BasicBlock> */
    public array $blocks = [];
    public int $entryBlockId = 0;

    public static function build(FunctionDef $func): self
    {
        $cfg = new self();
        $instructions = $func->body;
        $count = count($instructions);

        if ($count === 0) {
            return $cfg;
        }

        // 1. Identify leader instruction pointers
        $leaders = [0 => true];
        $targets = [];

        $controlStack = [];

        foreach ($instructions as $ip => $ins) {
            $op = $ins->opcode;

            if ($op === Opcode::BLOCK || $op === Opcode::LOOP || $op === Opcode::IF) {
                $controlStack[] = ['op' => $op, 'ip' => $ip, 'else' => null];
                $leaders[$ip + 1] = true;
            } elseif ($op === Opcode::ELSE) {
                if (!empty($controlStack)) {
                    $controlStack[count($controlStack) - 1]['else'] = $ip;
                }
                $leaders[$ip] = true;
                $leaders[$ip + 1] = true;
            } elseif ($op === Opcode::END) {
                if (!empty($controlStack)) {
                    array_pop($controlStack);
                }
                if ($ip + 1 < $count) {
                    $leaders[$ip + 1] = true;
                }
            } elseif ($op === Opcode::BR || $op === Opcode::BR_IF || $op === Opcode::RETURN || $op === Opcode::UNREACHABLE) {
                if ($ip + 1 < $count) {
                    $leaders[$ip + 1] = true;
                }
            }
        }

        $leaderIps = array_keys($leaders);
        sort($leaderIps);

        // 2. Form basic blocks
        $blockIdByIp = [];
        for ($i = 0; $i < count($leaderIps); $i++) {
            $start = $leaderIps[$i];
            $end = ($i + 1 < count($leaderIps)) ? $leaderIps[$i + 1] - 1 : $count - 1;

            $block = new BasicBlock($i, $start, $end);
            for ($k = $start; $k <= $end; $k++) {
                $block->instructions[] = $instructions[$k];
            }

            $cfg->blocks[$i] = $block;
            $blockIdByIp[$start] = $i;
        }

        // 3. Connect directed edges
        for ($i = 0; $i < count($cfg->blocks); $i++) {
            $block = $cfg->blocks[$i];
            $lastIns = $block->instructions[count($block->instructions) - 1] ?? null;

            if ($lastIns === null) {
                continue;
            }

            $op = $lastIns->opcode;

            if ($op !== Opcode::BR && $op !== Opcode::RETURN && $op !== Opcode::UNREACHABLE) {
                // Falls through to next sequential block
                if (isset($cfg->blocks[$i + 1])) {
                    $block->addSuccessor($i + 1);
                    $cfg->blocks[$i + 1]->addPredecessor($i);
                }
            }
        }

        return $cfg;
    }

    /**
     * Render control flow graph in Graphviz DOT format.
     */
    public function toDot(?string $functionName = null): string
    {
        $label = $functionName !== null ? "Function {$functionName}" : 'CFG';
        $out = ["digraph \"{$label}\" {"];
        $out[] = '  node [shape=box, fontname="Courier", fontsize=10];';
        $out[] = '  edge [fontname="Courier", fontsize=9];';

        foreach ($this->blocks as $block) {
            $insLines = [];
            foreach ($block->instructions as $ins) {
                $insLines[] = htmlspecialchars((string)$ins, ENT_QUOTES | ENT_SUBSTITUTE);
            }
            $body = implode('<br align="left"/>', $insLines);
            $labelHtml = sprintf('<<table border="0" cellborder="0" cellspacing="0"><tr><td><b>Block %d (IP %d..%d)</b></td></tr><hr/><tr><td align="left">%s</td></tr></table>>',
                $block->id,
                $block->startIp,
                $block->endIp,
                $body
            );
            $out[] = sprintf('  block_%d [label=%s];', $block->id, $labelHtml);
        }

        foreach ($this->blocks as $block) {
            foreach ($block->successors as $succId) {
                $out[] = sprintf('  block_%d -> block_%d;', $block->id, $succId);
            }
        }

        $out[] = '}';
        return implode("\n", $out) . "\n";
    }
}
