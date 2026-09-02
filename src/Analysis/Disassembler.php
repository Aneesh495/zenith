<?php

declare(strict_types=1);

namespace Zenith\Analysis;

use Zenith\Ast\FunctionDef;
use Zenith\Ast\Instruction;
use Zenith\Ast\Module;
use Zenith\Binary\Opcode;

/**
 * Bytecode disassembler providing annotated low-level instruction dumps.
 */
final class Disassembler
{
    public static function disassembleModule(Module $module): string
    {
        $lines = [];
        $lines[] = '=== Zenith WebAssembly Disassembly ===';
        $lines[] = sprintf('Version: %d | Functions: %d | Memories: %d | Tables: %d | Globals: %d',
            $module->version,
            $module->getTotalFunctionCount(),
            $module->getTotalMemoryCount(),
            $module->getTotalTableCount(),
            $module->getTotalGlobalCount()
        );
        $lines[] = '';

        $importedCount = $module->getImportedFunctionCount();
        foreach ($module->functions as $i => $func) {
            $funcIdx = $importedCount + $i;
            $nameStr = $func->debugName !== null ? " <\${$func->debugName}>" : '';
            $lines[] = sprintf('--- Function #%d%s (type %d) ---', $funcIdx, $nameStr, $func->typeIndex);
            $lines[] = self::disassembleFunction($func, $module);
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    public static function disassembleFunction(FunctionDef $func, ?Module $module = null): string
    {
        $lines = [];
        $indent = 2;

        foreach ($func->body as $ip => $ins) {
            if ($ins->is(Opcode::END) || $ins->is(Opcode::ELSE)) {
                $indent = max(2, $indent - 2);
            }

            $offsetStr = sprintf('%04x', $ins->offset !== 0 ? $ins->offset : $ip);
            $indentStr = str_repeat(' ', $indent);
            $mnemonic = $ins->getMnemonic();
            $immStr = (string)$ins;
            if (str_starts_with($immStr, $mnemonic)) {
                $immStr = trim(substr($immStr, strlen($mnemonic)));
            }

            // Annotations
            $annotation = '';
            if ($ins->is(Opcode::CALL) && $module !== null) {
                $targetFunc = $module->getFunction((int)$ins->immediate);
                if ($targetFunc !== null && $targetFunc->debugName !== null) {
                    $annotation = " ; -> \${$targetFunc->debugName}";
                }
            } elseif (($ins->is(Opcode::LOCAL_GET) || $ins->is(Opcode::LOCAL_SET) || $ins->is(Opcode::LOCAL_TEE))) {
                $lIdx = (int)$ins->immediate;
                if (isset($func->localNames[$lIdx])) {
                    $annotation = " ; \${$func->localNames[$lIdx]}";
                }
            }

            $lines[] = sprintf('  [%s] %s%-20s %-16s%s',
                $offsetStr,
                $indentStr,
                $mnemonic,
                $immStr,
                $annotation
            );

            if ($ins->is(Opcode::BLOCK) || $ins->is(Opcode::LOOP) || $ins->is(Opcode::IF) || $ins->is(Opcode::ELSE)) {
                $indent += 2;
            }
        }

        return implode("\n", $lines);
    }
}
