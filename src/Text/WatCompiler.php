<?php

declare(strict_types=1);

namespace Zenith\Text;

use RuntimeException;
use Zenith\Ast\BlockType;
use Zenith\Ast\CustomSection;
use Zenith\Ast\DataSegment;
use Zenith\Ast\ElementSegment;
use Zenith\Ast\Export;
use Zenith\Ast\ExternalKind;
use Zenith\Ast\FunctionDef;
use Zenith\Ast\FuncType;
use Zenith\Ast\GlobalType;
use Zenith\Ast\GlobalVar;
use Zenith\Ast\Import;
use Zenith\Ast\Instruction;
use Zenith\Ast\Limits;
use Zenith\Ast\Memory;
use Zenith\Ast\MemoryType;
use Zenith\Ast\Module;
use Zenith\Ast\Table;
use Zenith\Ast\TableType;
use Zenith\Ast\ValueType;
use Zenith\Binary\Opcode;

/**
 * WebAssembly Text format compiler.
 *
 * Compiles parsed WAT S-expressions into an executable Module AST.
 * Expands folded S-expression instructions into postfix bytecode,
 * resolves symbolic identifiers to index spaces, and computes relative label depths.
 */
final class WatCompiler
{
    private Module $module;

    /** @var array<string, int> */
    private array $funcIndices = [];
    /** @var array<string, int> */
    private array $globalIndices = [];
    /** @var array<string, int> */
    private array $tableIndices = [];
    /** @var array<string, int> */
    private array $memoryIndices = [];
    /** @var array<string, int> */
    private array $typeIndices = [];

    public function __construct()
    {
        $this->module = new Module();
    }

    public static function compile(string $watSource): Module
    {
        $lexer = new Lexer($watSource);
        $tokens = $lexer->tokenize();
        $parser = new Parser($tokens);
        $nodes = $parser->parse();

        $compiler = new self();
        return $compiler->compileModule($nodes);
    }

    /**
     * @param list<mixed> $nodes
     */
    public function compileModule(array $nodes): Module
    {
        $this->registeredFuncCount = 0;
        if (count($nodes) === 1 && is_array($nodes[0]) && !empty($nodes[0])) {
            $first = $nodes[0][0];
            if ($first instanceof Token && $first->isKeyword('module')) {
                $nodes = array_slice($nodes[0], 1);
                // Optional module identifier e.g. (module $m ...)
                if (!empty($nodes) && $nodes[0] instanceof Token && $nodes[0]->type === TokenType::Identifier) {
                    array_shift($nodes);
                }
            }
        }

        // Pass 1: Types and Imports
        foreach ($nodes as $node) {
            if (!is_array($node) || empty($node)) {
                continue;
            }
            $head = $node[0];
            if (!$head instanceof Token) {
                continue;
            }

            if ($head->isKeyword('type')) {
                $this->compileTypeDecl($node);
            } elseif ($head->isKeyword('import')) {
                $this->compileImportDecl($node);
            }
        }

        // Pass 2: Tables, Memories, Globals declarations
        foreach ($nodes as $node) {
            if (!is_array($node) || empty($node)) {
                continue;
            }
            $head = $node[0];
            if (!$head instanceof Token) {
                continue;
            }

            if ($head->isKeyword('table')) {
                $this->compileTableDecl($node);
            } elseif ($head->isKeyword('memory')) {
                $this->compileMemoryDecl($node);
            } elseif ($head->isKeyword('global')) {
                $this->compileGlobalDecl($node);
            }
        }

        // Pass 3: Function headers and symbol registration
        $funcNodes = [];
        foreach ($nodes as $node) {
            if (!is_array($node) || empty($node)) {
                continue;
            }
            $head = $node[0];
            if ($head instanceof Token && $head->isKeyword('func')) {
                $funcNodes[] = $node;
                $this->registerFuncHeader($node);
            }
        }

        // Pass 4: Function bodies
        foreach ($funcNodes as $node) {
            $this->compileFuncBody($node);
        }

        // Pass 5: Exports, Start, Elements, Data
        foreach ($nodes as $node) {
            if (!is_array($node) || empty($node)) {
                continue;
            }
            $head = $node[0];
            if (!$head instanceof Token) {
                continue;
            }

            if ($head->isKeyword('export')) {
                $this->compileExportDecl($node);
            } elseif ($head->isKeyword('start')) {
                $this->compileStartDecl($node);
            } elseif ($head->isKeyword('elem')) {
                $this->compileElemDecl($node);
            } elseif ($head->isKeyword('data')) {
                $this->compileDataDecl($node);
            }
        }

        return $this->module;
    }

    private function getOrRegisterType(FuncType $type): int
    {
        foreach ($this->module->types as $idx => $existing) {
            if ($existing->equals($type)) {
                return $idx;
            }
        }
        $this->module->types[] = $type;
        return count($this->module->types) - 1;
    }

    /**
     * @param list<mixed> $node
     */
    private function compileTypeDecl(array $node): void
    {
        // (type $id? (func (param ...) (result ...)))
        $cursor = 1;
        $id = null;
        if (isset($node[$cursor]) && $node[$cursor] instanceof Token && $node[$cursor]->type === TokenType::Identifier) {
            $id = $node[$cursor]->value;
            $cursor++;
        }

        $sub = $node[$cursor] ?? null;
        if (!is_array($sub) || empty($sub) || !$sub[0] instanceof Token || !$sub[0]->isKeyword('func')) {
            throw new RuntimeException('Expected func type signature inside type declaration');
        }

        $funcType = $this->parseFuncTypeSignature($sub);
        $typeIdx = $this->getOrRegisterType($funcType);

        if ($id !== null) {
            $this->typeIndices[$id] = $typeIdx;
        }
    }

    /**
     * @param list<mixed> $sub
     */
    private function parseFuncTypeSignature(array $sub): FuncType
    {
        $params = [];
        $results = [];

        for ($i = 1; $i < count($sub); $i++) {
            $elem = $sub[$i];
            if (!is_array($elem) || empty($elem)) {
                continue;
            }
            $tag = $elem[0];
            if (!$tag instanceof Token) {
                continue;
            }

            if ($tag->isKeyword('param')) {
                for ($j = 1; $j < count($elem); $j++) {
                    $item = $elem[$j];
                    if ($item instanceof Token) {
                        if ($item->type === TokenType::Identifier) {
                            continue; // skip parameter name
                        }
                        $params[] = ValueType::fromName($item->value);
                    }
                }
            } elseif ($tag->isKeyword('result')) {
                for ($j = 1; $j < count($elem); $j++) {
                    $item = $elem[$j];
                    if ($item instanceof Token) {
                        $results[] = ValueType::fromName($item->value);
                    }
                }
            }
        }

        return new FuncType($params, $results);
    }

    /**
     * @param list<mixed> $node
     */
    private function compileImportDecl(array $node): void
    {
        // (import "mod" "field" (desc ...))
        $modName = $node[1]->value;
        $fieldName = $node[2]->value;
        $descNode = $node[3];

        if (!is_array($descNode) || empty($descNode) || !$descNode[0] instanceof Token) {
            throw new RuntimeException('Malformed import descriptor');
        }

        $kindToken = $descNode[0];
        if ($kindToken->isKeyword('func')) {
            $cursor = 1;
            $id = null;
            if (isset($descNode[$cursor]) && $descNode[$cursor] instanceof Token && $descNode[$cursor]->type === TokenType::Identifier) {
                $id = $descNode[$cursor]->value;
                $cursor++;
            }

            $funcType = $this->parseFuncTypeSignature(array_merge([$kindToken], array_slice($descNode, $cursor)));
            $typeIdx = $this->getOrRegisterType($funcType);

            $funcIdx = $this->module->getTotalFunctionCount();
            if ($id !== null) {
                $this->funcIndices[$id] = $funcIdx;
            }

            $this->module->imports[] = new Import($modName, $fieldName, ExternalKind::Function, $typeIdx);
        } elseif ($kindToken->isKeyword('memory')) {
            $cursor = 1;
            $id = null;
            if (isset($descNode[$cursor]) && $descNode[$cursor] instanceof Token && $descNode[$cursor]->type === TokenType::Identifier) {
                $id = $descNode[$cursor]->value;
                $cursor++;
            }

            $limits = $this->parseLimits(array_slice($descNode, $cursor));
            $memIdx = $this->module->getTotalMemoryCount();
            if ($id !== null) {
                $this->memoryIndices[$id] = $memIdx;
            }

            $this->module->imports[] = new Import($modName, $fieldName, ExternalKind::Memory, new MemoryType($limits));
        } elseif ($kindToken->isKeyword('table')) {
            $cursor = 1;
            $id = null;
            if (isset($descNode[$cursor]) && $descNode[$cursor] instanceof Token && $descNode[$cursor]->type === TokenType::Identifier) {
                $id = $descNode[$cursor]->value;
                $cursor++;
            }

            $limits = $this->parseLimits(array_slice($descNode, $cursor, 2));
            $elemType = ValueType::fromName($descNode[count($descNode) - 1]->value);
            $tblIdx = $this->module->getTotalTableCount();
            if ($id !== null) {
                $this->tableIndices[$id] = $tblIdx;
            }

            $this->module->imports[] = new Import($modName, $fieldName, ExternalKind::Table, new TableType($elemType, $limits));
        } elseif ($kindToken->isKeyword('global')) {
            $cursor = 1;
            $id = null;
            if (isset($descNode[$cursor]) && $descNode[$cursor] instanceof Token && $descNode[$cursor]->type === TokenType::Identifier) {
                $id = $descNode[$cursor]->value;
                $cursor++;
            }

            $gType = $this->parseGlobalType($descNode[$cursor]);
            $gIdx = $this->module->getTotalGlobalCount();
            if ($id !== null) {
                $this->globalIndices[$id] = $gIdx;
            }

            $this->module->imports[] = new Import($modName, $fieldName, ExternalKind::Global, $gType);
        }
    }

    /**
     * @param list<mixed> $node
     */
    private function compileTableDecl(array $node): void
    {
        $cursor = 1;
        $id = null;
        if (isset($node[$cursor]) && $node[$cursor] instanceof Token && $node[$cursor]->type === TokenType::Identifier) {
            $id = $node[$cursor]->value;
            $cursor++;
        }

        // Inline export e.g. (export "tbl")
        while (isset($node[$cursor]) && is_array($node[$cursor]) && $node[$cursor][0] instanceof Token && $node[$cursor][0]->isKeyword('export')) {
            $expName = $node[$cursor][1]->value;
            $this->module->exports[] = new Export($expName, ExternalKind::Table, $this->module->getTotalTableCount());
            $cursor++;
        }

        $limits = $this->parseLimits(array_slice($node, $cursor, -1));
        $elemType = ValueType::fromName($node[count($node) - 1]->value);

        $tblIdx = $this->module->getTotalTableCount();
        if ($id !== null) {
            $this->tableIndices[$id] = $tblIdx;
        }

        $this->module->tables[] = new Table(new TableType($elemType, $limits));
    }

    /**
     * @param list<mixed> $node
     */
    private function compileMemoryDecl(array $node): void
    {
        $cursor = 1;
        $id = null;
        if (isset($node[$cursor]) && $node[$cursor] instanceof Token && $node[$cursor]->type === TokenType::Identifier) {
            $id = $node[$cursor]->value;
            $cursor++;
        }

        while (isset($node[$cursor]) && is_array($node[$cursor]) && $node[$cursor][0] instanceof Token && $node[$cursor][0]->isKeyword('export')) {
            $expName = $node[$cursor][1]->value;
            $this->module->exports[] = new Export($expName, ExternalKind::Memory, $this->module->getTotalMemoryCount());
            $cursor++;
        }

        $limits = $this->parseLimits(array_slice($node, $cursor));
        $memIdx = $this->module->getTotalMemoryCount();
        if ($id !== null) {
            $this->memoryIndices[$id] = $memIdx;
        }

        $this->module->memories[] = new Memory(new MemoryType($limits));
    }

    /**
     * @param list<mixed> $node
     */
    private function compileGlobalDecl(array $node): void
    {
        $cursor = 1;
        $id = null;
        if (isset($node[$cursor]) && $node[$cursor] instanceof Token && $node[$cursor]->type === TokenType::Identifier) {
            $id = $node[$cursor]->value;
            $cursor++;
        }

        while (isset($node[$cursor]) && is_array($node[$cursor]) && $node[$cursor][0] instanceof Token && $node[$cursor][0]->isKeyword('export')) {
            $expName = $node[$cursor][1]->value;
            $this->module->exports[] = new Export($expName, ExternalKind::Global, $this->module->getTotalGlobalCount());
            $cursor++;
        }

        $typeNode = $node[$cursor];
        $cursor++;
        $gType = $this->parseGlobalType($typeNode);

        $initExprNodes = array_slice($node, $cursor);
        $initExpr = $this->compileInstructions($initExprNodes, [], []);
        if (empty($initExpr) || !$initExpr[count($initExpr) - 1]->is(Opcode::END)) {
            $initExpr[] = new Instruction(Opcode::END);
        }

        $gIdx = $this->module->getTotalGlobalCount();
        if ($id !== null) {
            $this->globalIndices[$id] = $gIdx;
        }

        $this->module->globals[] = new GlobalVar($gType, $initExpr);
    }

    private function parseGlobalType(mixed $node): GlobalType
    {
        if (is_array($node) && !empty($node) && $node[0] instanceof Token && $node[0]->isKeyword('mut')) {
            $vt = ValueType::fromName($node[1]->value);
            return new GlobalType($vt, true);
        }

        if ($node instanceof Token) {
            $vt = ValueType::fromName($node->value);
            return new GlobalType($vt, false);
        }

        throw new RuntimeException('Malformed global type');
    }

    /**
     * @param list<mixed> $tokens
     */
    private function parseLimits(array $tokens): Limits
    {
        $min = (int)$tokens[0]->value;
        $max = isset($tokens[1]) && $tokens[1] instanceof Token ? (int)$tokens[1]->value : null;
        return new Limits($min, $max);
    }

    private int $registeredFuncCount = 0;

    /**
     * @param list<mixed> $node
     */
    private function registerFuncHeader(array $node): void
    {
        $cursor = 1;
        $id = null;
        if (isset($node[$cursor]) && $node[$cursor] instanceof Token && $node[$cursor]->type === TokenType::Identifier) {
            $id = $node[$cursor]->value;
            $cursor++;
        }

        $funcIdx = $this->module->getImportedFunctionCount() + $this->registeredFuncCount;
        $this->registeredFuncCount++;

        if ($id !== null) {
            $this->funcIndices[$id] = $funcIdx;
        }
    }

    /**
     * @param list<mixed> $node
     */
    private function compileFuncBody(array $node): void
    {
        $cursor = 1;
        $id = null;
        if (isset($node[$cursor]) && $node[$cursor] instanceof Token && $node[$cursor]->type === TokenType::Identifier) {
            $id = $node[$cursor]->value;
            $cursor++;
        }

        // Inline exports e.g. (export "name")
        $funcIdx = $this->module->getImportedFunctionCount() + count($this->module->functions);

        while (isset($node[$cursor]) && is_array($node[$cursor]) && !empty($node[$cursor]) && $node[$cursor][0] instanceof Token && $node[$cursor][0]->isKeyword('export')) {
            $expName = $node[$cursor][1]->value;
            $this->module->exports[] = new Export($expName, ExternalKind::Function, $funcIdx);
            $cursor++;
        }

        $params = [];
        $results = [];
        $locals = [];
        /** @var array<string, int> $localIndices */
        $localIndices = [];
        /** @var array<int, string> $localNames */
        $localNames = [];

        $explicitTypeIdx = null;

        // Parse (type ...), (param ...), (result ...), and (local ...)
        while (isset($node[$cursor]) && is_array($node[$cursor]) && !empty($node[$cursor]) && $node[$cursor][0] instanceof Token) {
            $tag = $node[$cursor][0];
            if ($tag->isKeyword('type')) {
                if (isset($node[$cursor][1]) && $node[$cursor][1] instanceof Token) {
                    $tTok = $node[$cursor][1];
                    $explicitTypeIdx = $tTok->type === TokenType::Identifier
                        ? ($this->typeIndices[$tTok->value] ?? null)
                        : (int)$tTok->value;
                }
                $cursor++;
            } elseif ($tag->isKeyword('param')) {
                for ($i = 1; $i < count($node[$cursor]); $i++) {
                    $item = $node[$cursor][$i];
                    if ($item instanceof Token) {
                        if ($item->type === TokenType::Identifier) {
                            $localIndices[$item->value] = count($params);
                            $localNames[count($params)] = $item->value;
                            $i++;
                            if ($i < count($node[$cursor])) {
                                $params[] = ValueType::fromName($node[$cursor][$i]->value);
                            }
                        } else {
                            $params[] = ValueType::fromName($item->value);
                        }
                    }
                }
                $cursor++;
            } elseif ($tag->isKeyword('result')) {
                for ($i = 1; $i < count($node[$cursor]); $i++) {
                    $item = $node[$cursor][$i];
                    if ($item instanceof Token) {
                        $results[] = ValueType::fromName($item->value);
                    }
                }
                $cursor++;
            } elseif ($tag->isKeyword('local')) {
                for ($i = 1; $i < count($node[$cursor]); $i++) {
                    $item = $node[$cursor][$i];
                    if ($item instanceof Token) {
                        $lIdx = count($params) + count($locals);
                        if ($item->type === TokenType::Identifier) {
                            $localIndices[$item->value] = $lIdx;
                            $localNames[$lIdx] = $item->value;
                            $i++;
                            if ($i < count($node[$cursor])) {
                                $locals[] = ValueType::fromName($node[$cursor][$i]->value);
                            }
                        } else {
                            $locals[] = ValueType::fromName($item->value);
                        }
                    }
                }
                $cursor++;
            } else {
                break;
            }
        }

        $funcType = new FuncType($params, $results);
        $typeIdx = $explicitTypeIdx ?? $this->getOrRegisterType($funcType);

        $bodyNodes = array_slice($node, $cursor);
        $instructions = $this->compileInstructions($bodyNodes, $localIndices, []);

        if (empty($instructions) || !$instructions[count($instructions) - 1]->is(Opcode::END)) {
            $instructions[] = new Instruction(Opcode::END);
        }

        $debugName = $id !== null ? ltrim($id, '$') : null;

        $funcDef = new FunctionDef($typeIdx, $locals, $instructions, $debugName, $localNames);
        $this->module->functions[] = $funcDef;
    }

    /**
     * @param list<mixed> $nodes
     * @param array<string, int> $localIndices
     * @param list<string|null> $labelStack
     * @return list<Instruction>
     */
    private function compileInstructions(array $nodes, array $localIndices, array $labelStack): array
    {
        $instructions = [];
        $count = count($nodes);
        $i = 0;

        while ($i < $count) {
            $node = $nodes[$i];
            $i++;

            if ($node instanceof Token) {
                $name = $node->value;
                $immediates = [];

                // Check if instruction takes immediates in flat token stream
                if (in_array($name, ['local.get', 'local.set', 'local.tee', 'global.get', 'global.set', 'call', 'br', 'br_if', 'i32.const', 'i64.const', 'f32.const', 'f64.const', 'ref.func', 'ref.null'], true)) {
                    if ($i < $count && ($nodes[$i] instanceof Token || is_array($nodes[$i]))) {
                        $immediates[] = $nodes[$i];
                        $i++;
                    }
                } elseif (str_contains($name, '.load') || str_contains($name, '.store')) {
                    while ($i < $count && $nodes[$i] instanceof Token && (str_starts_with($nodes[$i]->value, 'offset=') || str_starts_with($nodes[$i]->value, 'align='))) {
                        $immediates[] = $nodes[$i];
                        $i++;
                    }
                } elseif ($name === 'br_table') {
                    while ($i < $count && $nodes[$i] instanceof Token && ($nodes[$i]->type === TokenType::Identifier || $nodes[$i]->type === TokenType::Integer)) {
                        $immediates[] = $nodes[$i];
                        $i++;
                    }
                } elseif ($name === 'call_indirect') {
                    while ($i < $count && is_array($nodes[$i])) {
                        $sub = $nodes[$i];
                        if (!empty($sub) && $sub[0] instanceof Token && $sub[0]->isKeyword('type')) {
                            $immediates[] = $sub[1];
                        }
                        $i++;
                    }
                }

                $ins = $this->compileSingleInstruction($name, $immediates, $localIndices, $labelStack);
                if ($ins !== null) {
                    $instructions[] = $ins;
                }
            } elseif (is_array($node) && !empty($node)) {
                $head = $node[0];
                if (!$head instanceof Token) {
                    continue;
                }

                $name = $head->value;

                // Control structures
                if ($name === 'block' || $name === 'loop') {
                    $instructions = array_merge($instructions, $this->compileBlockOrLoop($node, $localIndices, $labelStack));
                } elseif ($name === 'if') {
                    $instructions = array_merge($instructions, $this->compileIf($node, $localIndices, $labelStack));
                } else {
                    // Folded instruction e.g. (i32.add (local.get 0) (local.get 1))
                    $args = array_slice($node, 1);
                    $subInstructions = [];
                    $immediates = [];

                    foreach ($args as $arg) {
                        if (is_array($arg)) {
                            if (!empty($arg) && $arg[0] instanceof Token && ($arg[0]->isKeyword('type') || $arg[0]->isKeyword('result'))) {
                                $immediates[] = $arg;
                            } else {
                                $subInstructions = array_merge($subInstructions, $this->compileInstructions([$arg], $localIndices, $labelStack));
                            }
                        } else {
                            $immediates[] = $arg;
                        }
                    }

                    // Prepend evaluation of sub-instructions
                    $instructions = array_merge($instructions, $subInstructions);

                    // Compile main instruction with immediates
                    $ins = $this->compileSingleInstruction($name, $immediates, $localIndices, $labelStack);
                    if ($ins !== null) {
                        $instructions[] = $ins;
                    }
                }
            }
        }

        return $instructions;
    }

    /**
     * @param list<mixed> $node
     * @param array<string, int> $localIndices
     * @param list<string|null> $labelStack
     * @return list<Instruction>
     */
    private function compileBlockOrLoop(array $node, array $localIndices, array $labelStack): array
    {
        $isLoop = $node[0]->value === 'loop';
        $opcode = $isLoop ? Opcode::LOOP : Opcode::BLOCK;

        $cursor = 1;
        $label = null;
        if (isset($node[$cursor]) && $node[$cursor] instanceof Token && $node[$cursor]->type === TokenType::Identifier) {
            $label = $node[$cursor]->value;
            $cursor++;
        }

        $blockType = BlockType::empty();
        if (isset($node[$cursor]) && is_array($node[$cursor]) && !empty($node[$cursor]) && $node[$cursor][0] instanceof Token && $node[$cursor][0]->isKeyword('result')) {
            $vt = ValueType::fromName($node[$cursor][1]->value);
            $blockType = BlockType::fromValueType($vt);
            $cursor++;
        }

        $newLabelStack = array_merge([$label], $labelStack);
        $bodyNodes = array_slice($node, $cursor);
        $body = $this->compileInstructions($bodyNodes, $localIndices, $newLabelStack);

        return array_merge(
            [new Instruction($opcode, $blockType)],
            $body,
            [new Instruction(Opcode::END)]
        );
    }

    /**
     * @param list<mixed> $node
     * @param array<string, int> $localIndices
     * @param list<string|null> $labelStack
     * @return list<Instruction>
     */
    private function compileIf(array $node, array $localIndices, array $labelStack): array
    {
        $cursor = 1;
        $label = null;
        if (isset($node[$cursor]) && $node[$cursor] instanceof Token && $node[$cursor]->type === TokenType::Identifier) {
            $label = $node[$cursor]->value;
            $cursor++;
        }

        $blockType = BlockType::empty();
        if (isset($node[$cursor]) && is_array($node[$cursor]) && !empty($node[$cursor]) && $node[$cursor][0] instanceof Token && $node[$cursor][0]->isKeyword('result')) {
            $vt = ValueType::fromName($node[$cursor][1]->value);
            $blockType = BlockType::fromValueType($vt);
            $cursor++;
        }

        $condInstructions = [];
        $thenInstructions = [];
        $elseInstructions = [];

        $newLabelStack = array_merge([$label], $labelStack);

        for ($i = $cursor; $i < count($node); $i++) {
            $elem = $node[$i];
            if (is_array($elem) && !empty($elem) && $elem[0] instanceof Token) {
                if ($elem[0]->isKeyword('then')) {
                    $thenInstructions = $this->compileInstructions(array_slice($elem, 1), $localIndices, $newLabelStack);
                } elseif ($elem[0]->isKeyword('else')) {
                    $elseInstructions = $this->compileInstructions(array_slice($elem, 1), $localIndices, $newLabelStack);
                } else {
                    $condInstructions = array_merge($condInstructions, $this->compileInstructions([$elem], $localIndices, $labelStack));
                }
            } else {
                $condInstructions = array_merge($condInstructions, $this->compileInstructions([$elem], $localIndices, $labelStack));
            }
        }

        $out = $condInstructions;
        $out[] = new Instruction(Opcode::IF, $blockType);
        $out = array_merge($out, $thenInstructions);

        if (!empty($elseInstructions)) {
            $out[] = new Instruction(Opcode::ELSE);
            $out = array_merge($out, $elseInstructions);
        }

        $out[] = new Instruction(Opcode::END);
        return $out;
    }

    /**
     * @param list<mixed>|null $immediates
     * @param array<string, int> $localIndices
     * @param list<string|null> $labelStack
     */
    private function compileSingleInstruction(string $mnemonic, ?array $immediates, array $localIndices, array $labelStack): ?Instruction
    {
        $opcode = Opcode::getOpcodeByName($mnemonic);
        if ($opcode === null) {
            throw new RuntimeException("Unknown instruction mnemonic: {$mnemonic}");
        }

        $imm = null;

        if ($opcode === Opcode::LOCAL_GET || $opcode === Opcode::LOCAL_SET || $opcode === Opcode::LOCAL_TEE) {
            $target = $immediates[0] ?? null;
            if ($target instanceof Token) {
                $imm = $target->type === TokenType::Identifier
                    ? ($localIndices[$target->value] ?? throw new RuntimeException("Unknown local {$target->value}"))
                    : (int)$target->value;
            } else {
                $imm = 0;
            }
        } elseif ($opcode === Opcode::GLOBAL_GET || $opcode === Opcode::GLOBAL_SET) {
            $target = $immediates[0] ?? null;
            if ($target instanceof Token) {
                $imm = $target->type === TokenType::Identifier
                    ? ($this->globalIndices[$target->value] ?? throw new RuntimeException("Unknown global {$target->value}"))
                    : (int)$target->value;
            }
        } elseif ($opcode === Opcode::CALL) {
            $target = $immediates[0] ?? null;
            if ($target instanceof Token) {
                $imm = $target->type === TokenType::Identifier
                    ? ($this->funcIndices[$target->value] ?? throw new RuntimeException("Unknown function {$target->value}"))
                    : (int)$target->value;
            }
        } elseif ($opcode === Opcode::BR || $opcode === Opcode::BR_IF) {
            $target = $immediates[0] ?? null;
            if ($target instanceof Token) {
                if ($target->type === TokenType::Identifier) {
                    $foundDepth = array_search($target->value, $labelStack, true);
                    if ($foundDepth === false) {
                        throw new RuntimeException("Unknown branch label {$target->value}");
                    }
                    $imm = $foundDepth;
                } else {
                    $imm = (int)$target->value;
                }
            }
        } elseif ($opcode === Opcode::BR_TABLE) {
            $resolvedDepths = [];
            if ($immediates !== null) {
                foreach ($immediates as $im) {
                    if ($im instanceof Token) {
                        if ($im->type === TokenType::Identifier) {
                            $idx = array_search($im->value, $labelStack, true);
                            if ($idx === false) {
                                throw new RuntimeException("Unknown branch label {$im->value} in br_table");
                            }
                            $resolvedDepths[] = $idx;
                        } else {
                            $resolvedDepths[] = (int)$im->value;
                        }
                    }
                }
            }
            $defaultDepth = array_pop($resolvedDepths) ?? 0;
            $imm = ['targets' => $resolvedDepths, 'default' => $defaultDepth];
        } elseif ($opcode === Opcode::I32_CONST) {
            $token = $immediates[0] ?? null;
            $raw = $token instanceof Token ? $token->value : (string)$token;
            $imm = str_starts_with($raw, '0x') || str_starts_with($raw, '-0x') || str_starts_with($raw, '+0x')
                ? (int)hexdec($raw)
                : (int)$raw;
        } elseif ($opcode === Opcode::I64_CONST) {
            $token = $immediates[0] ?? null;
            $raw = $token instanceof Token ? $token->value : (string)$token;
            $imm = str_starts_with($raw, '0x') || str_starts_with($raw, '-0x') || str_starts_with($raw, '+0x')
                ? (int)hexdec($raw)
                : (int)$raw;
        } elseif ($opcode === Opcode::F32_CONST || $opcode === Opcode::F64_CONST) {
            $token = $immediates[0] ?? null;
            $raw = $token instanceof Token ? $token->value : (string)$token;
            if (strtolower($raw) === 'nan') {
                $imm = NAN;
            } elseif (strtolower($raw) === 'inf' || strtolower($raw) === '+inf') {
                $imm = INF;
            } elseif (strtolower($raw) === '-inf') {
                $imm = -INF;
            } else {
                $imm = (float)$raw;
            }
        } elseif ($opcode === Opcode::CALL_INDIRECT) {
            $typeIdx = 0;
            $tableIdx = 0;
            if ($immediates !== null) {
                foreach ($immediates as $im) {
                    if ($im instanceof Token) {
                        $typeIdx = $im->type === TokenType::Identifier
                            ? ($this->typeIndices[$im->value] ?? throw new RuntimeException("Unknown type {$im->value}"))
                            : (int)$im->value;
                    } elseif (is_array($im) && !empty($im) && $im[0] instanceof Token && $im[0]->isKeyword('type')) {
                        $target = $im[1] ?? null;
                        if ($target instanceof Token) {
                            $typeIdx = $target->type === TokenType::Identifier
                                ? ($this->typeIndices[$target->value] ?? throw new RuntimeException("Unknown type {$target->value}"))
                                : (int)$target->value;
                        }
                    }
                }
            }
            $imm = ['typeIndex' => $typeIdx, 'tableIndex' => $tableIdx];
        } elseif (Opcode::isMemoryLoad($opcode) || Opcode::isMemoryStore($opcode)) {
            $align = 0;
            $offset = 0;
            if ($immediates !== null) {
                foreach ($immediates as $im) {
                    if ($im instanceof Token) {
                        if (str_starts_with($im->value, 'offset=')) {
                            $offset = (int)substr($im->value, 7);
                        } elseif (str_starts_with($im->value, 'align=')) {
                            $align = (int)substr($im->value, 6);
                        }
                    }
                }
            }
            $imm = ['align' => $align, 'offset' => $offset];
        }

        return new Instruction($opcode, $imm);
    }

    /**
     * @param list<mixed> $node
     */
    private function compileExportDecl(array $node): void
    {
        $name = $node[1]->value;
        $desc = $node[2];

        if (!is_array($desc) || empty($desc) || !$desc[0] instanceof Token) {
            throw new RuntimeException('Malformed export descriptor');
        }

        $kind = ExternalKind::tryFrom($desc[0]->value === 'func' ? 0 : ($desc[0]->value === 'table' ? 1 : ($desc[0]->value === 'memory' ? 2 : 3)));
        $target = $desc[1];

        $idx = 0;
        if ($target instanceof Token) {
            if ($target->type === TokenType::Identifier) {
                $idx = match ($kind) {
                    ExternalKind::Function => $this->funcIndices[$target->value] ?? throw new RuntimeException("Unknown func {$target->value}"),
                    ExternalKind::Table => $this->tableIndices[$target->value] ?? throw new RuntimeException("Unknown table {$target->value}"),
                    ExternalKind::Memory => $this->memoryIndices[$target->value] ?? throw new RuntimeException("Unknown memory {$target->value}"),
                    ExternalKind::Global => $this->globalIndices[$target->value] ?? throw new RuntimeException("Unknown global {$target->value}"),
                    default => 0,
                };
            } else {
                $idx = (int)$target->value;
            }
        }

        $this->module->exports[] = new Export($name, $kind, $idx);
    }

    /**
     * @param list<mixed> $node
     */
    private function compileStartDecl(array $node): void
    {
        $target = $node[1];
        if ($target instanceof Token) {
            $this->module->start = $target->type === TokenType::Identifier
                ? ($this->funcIndices[$target->value] ?? throw new RuntimeException("Unknown start func {$target->value}"))
                : (int)$target->value;
        }
    }

    /**
     * @param list<mixed> $node
     */
    private function compileElemDecl(array $node): void
    {
        $cursor = 1;
        $tableIdx = 0;

        if (isset($node[$cursor]) && $node[$cursor] instanceof Token && $node[$cursor]->type === TokenType::Integer) {
            $tableIdx = (int)$node[$cursor]->value;
            $cursor++;
        }

        $offsetExpr = [];
        if (isset($node[$cursor]) && is_array($node[$cursor])) {
            $offsetExpr = $this->compileInstructions([$node[$cursor]], [], []);
            if (empty($offsetExpr) || !$offsetExpr[count($offsetExpr) - 1]->is(Opcode::END)) {
                $offsetExpr[] = new Instruction(Opcode::END);
            }
            $cursor++;
        }

        $elements = [];
        for ($i = $cursor; $i < count($node); $i++) {
            $item = $node[$i];
            if ($item instanceof Token) {
                $elements[] = $item->type === TokenType::Identifier
                    ? ($this->funcIndices[$item->value] ?? throw new RuntimeException("Unknown elem func {$item->value}"))
                    : (int)$item->value;
            }
        }

        $this->module->elements[] = new ElementSegment(
            ElementSegment::MODE_ACTIVE,
            $tableIdx,
            $offsetExpr,
            ValueType::FuncRef,
            $elements
        );
    }

    /**
     * @param list<mixed> $node
     */
    private function compileDataDecl(array $node): void
    {
        $cursor = 1;
        $memIdx = 0;

        if (isset($node[$cursor]) && $node[$cursor] instanceof Token && $node[$cursor]->type === TokenType::Integer) {
            $memIdx = (int)$node[$cursor]->value;
            $cursor++;
        }

        $offsetExpr = [];
        if (isset($node[$cursor]) && is_array($node[$cursor])) {
            $offsetExpr = $this->compileInstructions([$node[$cursor]], [], []);
            if (empty($offsetExpr) || !$offsetExpr[count($offsetExpr) - 1]->is(Opcode::END)) {
                $offsetExpr[] = new Instruction(Opcode::END);
            }
            $cursor++;
        }

        $data = '';
        if (isset($node[$cursor]) && $node[$cursor] instanceof Token && $node[$cursor]->type === TokenType::String) {
            $data = $node[$cursor]->value;
        }

        $this->module->data[] = new DataSegment(
            DataSegment::MODE_ACTIVE,
            $memIdx,
            $offsetExpr,
            $data
        );
    }
}
