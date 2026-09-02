<?php

declare(strict_types=1);

namespace Zenith\Analysis;

use Zenith\Ast\Instruction;

/**
 * Representation of a basic block in a function control flow graph.
 */
final class BasicBlock
{
    /**
     * @param int $id Unique block index in CFG
     * @param int $startIp Instruction pointer of block start
     * @param int $endIp Instruction pointer of block end
     * @param list<Instruction> $instructions Contained instructions
     * @param list<int> $predecessors Predecessor block IDs
     * @param list<int> $successors Successor block IDs
     */
    public function __construct(
        public readonly int $id,
        public readonly int $startIp,
        public int $endIp,
        public array $instructions = [],
        public array $predecessors = [],
        public array $successors = [],
    ) {
    }

    public function addSuccessor(int $blockId): void
    {
        if (!in_array($blockId, $this->successors, true)) {
            $this->successors[] = $blockId;
        }
    }

    public function addPredecessor(int $blockId): void
    {
        if (!in_array($blockId, $this->predecessors, true)) {
            $this->predecessors[] = $blockId;
        }
    }
}
