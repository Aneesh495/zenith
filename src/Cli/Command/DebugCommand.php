<?php

declare(strict_types=1);

namespace Zenith\Cli\Command;

use Zenith\Binary\ModuleDecoder;
use Zenith\Cli\ConsoleOutput;
use Zenith\Debugger\Stepper;
use Zenith\Runtime\Store;
use Zenith\Runtime\Vm;
use Zenith\Text\WatCompiler;
use Zenith\Wasi\WasiContext;
use Zenith\Wasi\WasiSnapshotPreview1;

/**
 * CLI command to launch the interactive terminal step debugger.
 */
final class DebugCommand
{
    /**
     * @param list<string> $args
     */
    public static function execute(array $args): int
    {
        if (empty($args)) {
            ConsoleOutput::error('Usage: zenith debug <file.wasm|file.wat> [--invoke <func>] [args...]');
            return 1;
        }

        $file = $args[0];
        if (!file_exists($file)) {
            ConsoleOutput::error("File not found: {$file}");
            return 1;
        }

        $invokeFunc = null;
        $programArgs = [];

        for ($i = 1; $i < count($args); $i++) {
            $arg = $args[$i];
            if ($arg === '--invoke' && isset($args[$i + 1])) {
                $invokeFunc = $args[$i + 1];
                $i++;
            } else {
                $programArgs[] = $arg;
            }
        }

        $content = file_get_contents($file);
        if ($content === false) {
            ConsoleOutput::error("Failed to read file: {$file}");
            return 1;
        }

        $isBinary = str_starts_with($content, ModuleDecoder::WASM_MAGIC);
        $module = $isBinary ? ModuleDecoder::decode($content) : WatCompiler::compile($content);

        $vm = new Vm();
        $store = new Store($vm);
        $wasiContext = new WasiContext(array_merge([$file], $programArgs), $_ENV);
        WasiSnapshotPreview1::bind($store, $wasiContext);

        $stepper = new Stepper();
        $stepper->attach($vm);

        try {
            $instance = $store->instantiate($module);

            if ($invokeFunc !== null) {
                $instance->invoke($invokeFunc, $programArgs);
            } elseif ($instance->getExport('_start') !== null) {
                $instance->invoke('_start');
            } elseif ($instance->getExport('main') !== null) {
                $instance->invoke('main', $programArgs);
            } else {
                ConsoleOutput::warning('No _start or main function to debug. Specify --invoke <func>.');
            }

            return 0;
        } catch (\Throwable $e) {
            ConsoleOutput::error('Debugger terminated: ' . $e->getMessage());
            return 1;
        }
    }
}
