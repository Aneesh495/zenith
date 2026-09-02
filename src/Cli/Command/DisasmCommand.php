<?php

declare(strict_types=1);

namespace Zenith\Cli\Command;

use Zenith\Analysis\ControlFlowGraph;
use Zenith\Analysis\Disassembler;
use Zenith\Binary\ModuleDecoder;
use Zenith\Cli\ConsoleOutput;
use Zenith\Text\WatCompiler;
use Zenith\Text\WatPrinter;

/**
 * CLI command to disassemble and decompile WebAssembly modules.
 */
final class DisasmCommand
{
    /**
     * @param list<string> $args
     */
    public static function execute(array $args): int
    {
        if (empty($args)) {
            ConsoleOutput::error('Usage: zenith disasm <file.wasm|file.wat> [--wat] [--cfg]');
            return 1;
        }

        $file = $args[0];
        if (!file_exists($file)) {
            ConsoleOutput::error("File not found: {$file}");
            return 1;
        }

        $asWat = in_array('--wat', $args, true);
        $asCfg = in_array('--cfg', $args, true);

        $content = file_get_contents($file);
        if ($content === false) {
            ConsoleOutput::error("Failed to read file: {$file}");
            return 1;
        }

        $isBinary = str_starts_with($content, ModuleDecoder::WASM_MAGIC);
        $module = $isBinary ? ModuleDecoder::decode($content) : WatCompiler::compile($content);

        if ($asWat) {
            echo WatPrinter::print($module);
            return 0;
        }

        if ($asCfg) {
            foreach ($module->functions as $i => $func) {
                $name = $func->debugName ?? (string)$i;
                $cfg = ControlFlowGraph::build($func);
                echo $cfg->toDot($name);
            }
            return 0;
        }

        echo Disassembler::disassembleModule($module);
        return 0;
    }
}
