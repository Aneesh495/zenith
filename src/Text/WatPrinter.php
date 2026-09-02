<?php

declare(strict_types=1);

namespace Zenith\Text;

use Zenith\Ast\DataSegment;
use Zenith\Ast\ElementSegment;
use Zenith\Ast\Export;
use Zenith\Ast\ExternalKind;
use Zenith\Ast\FunctionDef;
use Zenith\Ast\FuncType;
use Zenith\Ast\GlobalVar;
use Zenith\Ast\Import;
use Zenith\Ast\Instruction;
use Zenith\Ast\Memory;
use Zenith\Ast\Module;
use Zenith\Ast\Table;
use Zenith\Ast\ValueType;
use Zenith\Binary\Opcode;

/**
 * WebAssembly Text format pretty printer and decompiler.
 *
 * Formats in-memory Module ASTs into clean, indented .wat S-expressions
 * with resolved symbol names and proper block nesting.
 */
final class WatPrinter
{
    public static function print(Module $module): string
    {
        $lines = ['(module'];

        // Types
        foreach ($module->types as $idx => $type) {
            $lines[] = sprintf('  (type (;%d;) (func %s))', $idx, (string)$type);
        }

        // Imports
        foreach ($module->imports as $idx => $imp) {
            $descStr = match ($imp->kind) {
                ExternalKind::Function => sprintf('(func (;%d;) (type %d))', $idx, $imp->getTypeIndex()),
                ExternalKind::Memory => sprintf('(memory %d%s)', $imp->desc->limits->min, $imp->desc->limits->max !== null ? ' ' . $imp->desc->limits->max : ''),
                ExternalKind::Table => sprintf('(table %d %s)', $imp->desc->limits->min, $imp->desc->elemType->name()),
                ExternalKind::Global => sprintf('(global %s)', $imp->desc->mutable ? '(mut ' . $imp->desc->valType->name() . ')' : $imp->desc->valType->name()),
            };
            $lines[] = sprintf('  (import "%s" "%s" %s)', $imp->module, $imp->field, $descStr);
        }

        // Tables
        foreach ($module->tables as $idx => $tbl) {
            $maxStr = $tbl->type->limits->max !== null ? ' ' . $tbl->type->limits->max : '';
            $lines[] = sprintf('  (table (;%d;) %d%s %s)', $idx, $tbl->type->limits->min, $maxStr, $tbl->type->elemType->name());
        }

        // Memories
        foreach ($module->memories as $idx => $mem) {
            $maxStr = $mem->type->limits->max !== null ? ' ' . $mem->type->limits->max : '';
            $lines[] = sprintf('  (memory (;%d;) %d%s)', $idx, $mem->type->limits->min, $maxStr);
        }

        // Globals
        foreach ($module->globals as $idx => $global) {
            $typeStr = $global->type->mutable ? '(mut ' . $global->type->valType->name() . ')' : $global->type->valType->name();
            $initStr = self::printInstructions($global->initExpr, 0, false);
            $lines[] = sprintf('  (global (;%d;) %s %s)', $idx, $typeStr, trim($initStr));
        }

        // Exports
        foreach ($module->exports as $exp) {
            $kindStr = $exp->kind->name();
            $lines[] = sprintf('  (export "%s" (%s %d))', $exp->name, $kindStr, $exp->index);
        }

        // Start
        if ($module->start !== null) {
            $lines[] = sprintf('  (start %d)', $module->start);
        }

        // Elements
        foreach ($module->elements as $idx => $elem) {
            $offsetStr = self::printInstructions($elem->offsetExpr, 0, false);
            $funcList = implode(' ', $elem->elements);
            $lines[] = sprintf('  (elem (;%d;) %s func %s)', $idx, trim($offsetStr), $funcList);
        }

        // Functions
        $importedCount = $module->getImportedFunctionCount();
        foreach ($module->functions as $idx => $func) {
            $funcIdx = $importedCount + $idx;
            $type = $module->types[$func->typeIndex] ?? new FuncType();
            $nameStr = $func->debugName !== null ? ' $' . $func->debugName : sprintf(' (;%d;)', $funcIdx);

            $sigParts = [];
            if (!empty($type->params)) {
                $pNames = array_map(static fn(ValueType $t): string => $t->name(), $type->params);
                $sigParts[] = '(param ' . implode(' ', $pNames) . ')';
            }
            if (!empty($type->results)) {
                $rNames = array_map(static fn(ValueType $t): string => $t->name(), $type->results);
                $sigParts[] = '(result ' . implode(' ', $rNames) . ')';
            }
            $sigStr = !empty($sigParts) ? ' ' . implode(' ', $sigParts) : '';

            $lines[] = sprintf('  (func%s (type %d)%s', $nameStr, $func->typeIndex, $sigStr);

            if (!empty($func->locals)) {
                $lNames = array_map(static fn(ValueType $t): string => $t->name(), $func->locals);
                $lines[] = '    (local ' . implode(' ', $lNames) . ')';
            }

            $bodyStr = self::printInstructions($func->body, 4, true);
            if ($bodyStr !== '') {
                $lines[] = $bodyStr;
            }
            $lines[] = '  )';
        }

        // Data
        foreach ($module->data as $idx => $data) {
            $offsetStr = self::printInstructions($data->offsetExpr, 0, false);
            $escaped = addcslashes($data->data, "\\\"\n\r\t\0..\37\177..\377");
            $lines[] = sprintf('  (data (;%d;) %s "%s")', $idx, trim($offsetStr), $escaped);
        }

        $lines[] = ')';
        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<Instruction> $instructions
     */
    public static function printInstructions(array $instructions, int $baseIndent = 0, bool $multiline = true): string
    {
        $lines = [];
        $indent = $baseIndent;

        foreach ($instructions as $ins) {
            if ($ins->is(Opcode::END) || $ins->is(Opcode::ELSE)) {
                $indent = max($baseIndent, $indent - 2);
            }

            $indentStr = str_repeat(' ', $indent);
            $text = (string)$ins;

            if ($multiline) {
                $lines[] = $indentStr . $text;
            } else {
                $lines[] = $text;
            }

            if ($ins->is(Opcode::BLOCK) || $ins->is(Opcode::LOOP) || $ins->is(Opcode::IF) || $ins->is(Opcode::ELSE)) {
                $indent += 2;
            }
        }

        return implode($multiline ? "\n" : ' ', $lines);
    }
}
