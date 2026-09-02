<?php

declare(strict_types=1);

namespace Zenith\Cli\Command;

use Zenith\Binary\ModuleDecoder;
use Zenith\Cli\ConsoleOutput;
use Zenith\Text\WatCompiler;
use Zenith\Validator\ValidationError;
use Zenith\Validator\Validator;

/**
 * CLI command to statically validate WebAssembly modules against the Wasm 2.0 type system.
 */
final class ValidateCommand
{
    /**
     * @param list<string> $args
     */
    public static function execute(array $args): int
    {
        if (empty($args)) {
            ConsoleOutput::error('Usage: zenith validate <file.wasm|file.wat>');
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

        try {
            $isBinary = str_starts_with($content, ModuleDecoder::WASM_MAGIC);
            $module = $isBinary ? ModuleDecoder::decode($content) : WatCompiler::compile($content);

            Validator::validate($module);

            ConsoleOutput::success("Validation successful: '{$file}' conforms to the WebAssembly 2.0 specification.");
            echo sprintf("  Functions : %d\n", $module->getTotalFunctionCount());
            echo sprintf("  Memories  : %d\n", $module->getTotalMemoryCount());
            echo sprintf("  Tables    : %d\n", $module->getTotalTableCount());
            echo sprintf("  Globals   : %d\n", $module->getTotalGlobalCount());
            echo sprintf("  Exports   : %d\n", count($module->exports));
            return 0;
        } catch (ValidationError $e) {
            ConsoleOutput::error("Validation failed: " . $e->getMessage());
            return 1;
        } catch (\Throwable $e) {
            ConsoleOutput::error("Error parsing module: " . $e->getMessage());
            return 1;
        }
    }
}
