<?php

declare(strict_types=1);

namespace Zenith\Cli\Command;

use Zenith\Binary\ModuleDecoder;
use Zenith\Cli\ConsoleOutput;
use Zenith\Debugger\Terminal;
use Zenith\Text\WatCompiler;

/**
 * CLI command to inspect module headers, sections, imports, exports, and internal structures.
 */
final class InspectCommand
{
    /**
     * @param list<string> $args
     */
    public static function execute(array $args): int
    {
        if (empty($args)) {
            ConsoleOutput::error('Usage: zenith inspect <file.wasm|file.wat>');
            return 1;
        }

        $file = $args[0];
        if (!file_exists($file)) {
            ConsoleOutput::error("File not found: {$file}");
            return 1;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            ConsoleOutput::error("Failed to read file: {$file}");
            return 1;
        }

        $isBinary = str_starts_with($content, ModuleDecoder::WASM_MAGIC);
        $module = $isBinary ? ModuleDecoder::decode($content) : WatCompiler::compile($content);

        ConsoleOutput::header("Module Inspection: {$file}");
        echo sprintf("Format: %s | Size: %d bytes | Version: %d\n\n",
            $isBinary ? 'Binary (.wasm)' : 'Text (.wat)',
            strlen($content),
            $module->version
        );

        // Types Section
        echo Terminal::color("Types (" . count($module->types) . "):\n", Terminal::BOLD . Terminal::CYAN);
        foreach ($module->types as $i => $t) {
            echo sprintf("  [%d] %s\n", $i, (string)$t);
        }
        echo "\n";

        // Imports Section
        echo Terminal::color("Imports (" . count($module->imports) . "):\n", Terminal::BOLD . Terminal::CYAN);
        if (empty($module->imports)) {
            echo "  <none>\n";
        } else {
            foreach ($module->imports as $i => $imp) {
                echo sprintf("  [%d] %s::%s (%s)\n", $i, $imp->module, $imp->field, $imp->kind->name());
            }
        }
        echo "\n";

        // Exports Section
        echo Terminal::color("Exports (" . count($module->exports) . "):\n", Terminal::BOLD . Terminal::CYAN);
        if (empty($module->exports)) {
            echo "  <none>\n";
        } else {
            foreach ($module->exports as $exp) {
                echo sprintf("  \"%s\" -> %s #%d\n", $exp->name, $exp->kind->name(), $exp->index);
            }
        }
        echo "\n";

        // Functions Section
        $importedFuncs = $module->getImportedFunctionCount();
        echo Terminal::color("Functions (" . $module->getTotalFunctionCount() . " total, " . count($module->functions) . " defined):\n", Terminal::BOLD . Terminal::CYAN);
        foreach ($module->functions as $i => $f) {
            $fIdx = $importedFuncs + $i;
            $nameStr = $f->debugName !== null ? " <\${$f->debugName}>" : '';
            $insCount = count($f->body);
            $localCount = count($f->locals);
            echo sprintf("  #%-3d%s (type %d) : %d instructions, %d locals\n",
                $fIdx,
                $nameStr,
                $f->typeIndex,
                $insCount,
                $localCount
            );
        }
        echo "\n";

        // Memories Section
        echo Terminal::color("Memories (" . $module->getTotalMemoryCount() . "):\n", Terminal::BOLD . Terminal::CYAN);
        foreach ($module->memories as $i => $m) {
            $maxStr = $m->type->limits->max !== null ? ", max {$m->type->limits->max}" : '';
            echo sprintf("  [%d] Initial %d pages (64 KiB/page)%s\n", $i, $m->type->limits->min, $maxStr);
        }
        echo "\n";

        // Tables Section
        echo Terminal::color("Tables (" . $module->getTotalTableCount() . "):\n", Terminal::BOLD . Terminal::CYAN);
        foreach ($module->tables as $i => $t) {
            $maxStr = $t->type->limits->max !== null ? ", max {$t->type->limits->max}" : '';
            echo sprintf("  [%d] Initial %d elements (%s)%s\n", $i, $t->type->limits->min, $t->type->elemType->name(), $maxStr);
        }
        echo "\n";

        // Globals Section
        echo Terminal::color("Globals (" . $module->getTotalGlobalCount() . "):\n", Terminal::BOLD . Terminal::CYAN);
        foreach ($module->globals as $i => $g) {
            echo sprintf("  [%d] %s (%s)\n", $i, $g->type->valType->name(), $g->type->mutable ? 'mutable' : 'immutable');
        }

        return 0;
    }
}
