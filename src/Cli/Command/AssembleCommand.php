<?php

declare(strict_types=1);

namespace Zenith\Cli\Command;

use Zenith\Analysis\Optimizer;
use Zenith\Binary\ModuleEncoder;
use Zenith\Cli\ConsoleOutput;
use Zenith\Text\WatCompiler;

/**
 * CLI command to assemble WAT text files into binary WebAssembly modules.
 */
final class AssembleCommand
{
    /**
     * @param list<string> $args
     */
    public static function execute(array $args): int
    {
        if (empty($args)) {
            ConsoleOutput::error('Usage: zenith assemble <file.wat> -o <file.wasm> [-O]');
            return 1;
        }

        $inputFile = $args[0];
        $outputFile = null;
        $optimize = false;

        for ($i = 1; $i < count($args); $i++) {
            if ($args[$i] === '-o' && isset($args[$i + 1])) {
                $outputFile = $args[$i + 1];
                $i++;
            } elseif ($args[$i] === '-O' || $args[$i] === '--optimize') {
                $optimize = true;
            }
        }

        if ($outputFile === null) {
            $outputFile = preg_replace('/\.wat$/i', '', $inputFile) . '.wasm';
        }

        if (!file_exists($inputFile)) {
            ConsoleOutput::error("Input file not found: {$inputFile}");
            return 1;
        }

        $wat = file_get_contents($inputFile);
        if ($wat === false) {
            ConsoleOutput::error("Failed to read input file: {$inputFile}");
            return 1;
        }

        try {
            $module = WatCompiler::compile($wat);

            if ($optimize) {
                $opts = Optimizer::optimizeModule($module);
                ConsoleOutput::info("Applied {$opts} bytecode optimization passes");
            }

            $binary = ModuleEncoder::encode($module);
            file_put_contents($outputFile, $binary);

            ConsoleOutput::success(sprintf(
                'Successfully assembled %s -> %s (%d bytes)',
                $inputFile,
                $outputFile,
                strlen($binary)
            ));
            return 0;
        } catch (\Throwable $e) {
            ConsoleOutput::error('Assembly failed: ' . $e->getMessage());
            return 1;
        }
    }
}
