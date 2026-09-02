<?php

declare(strict_types=1);

namespace Zenith\Cli\Command;

use RuntimeException;
use Zenith\Analysis\Profiler;
use Zenith\Binary\ModuleDecoder;
use Zenith\Cli\ConsoleOutput;
use Zenith\Runtime\Store;
use Zenith\Runtime\Vm;
use Zenith\Text\WatCompiler;
use Zenith\Wasi\WasiContext;
use Zenith\Wasi\WasiExitException;
use Zenith\Wasi\WasiSnapshotPreview1;

/**
 * CLI command to execute WebAssembly binaries or WAT source files.
 */
final class RunCommand
{
    /**
     * @param list<string> $args
     */
    public static function execute(array $args): int
    {
        if (empty($args)) {
            ConsoleOutput::error('Usage: zenith run <file.wasm|file.wat> [--invoke <func>] [--profile] [args...]');
            return 1;
        }

        $file = $args[0];
        if (!file_exists($file)) {
            ConsoleOutput::error("File not found: {$file}");
            return 1;
        }

        $invokeFunc = null;
        $profile = false;
        $programArgs = [];

        for ($i = 1; $i < count($args); $i++) {
            $arg = $args[$i];
            if ($arg === '--invoke' && isset($args[$i + 1])) {
                $invokeFunc = $args[$i + 1];
                $i++;
            } elseif (str_starts_with($arg, '--invoke=')) {
                $invokeFunc = substr($arg, 9);
            } elseif ($arg === '--profile') {
                $profile = true;
            } else {
                $programArgs[] = $arg;
            }
        }

        $content = file_get_contents($file);
        if ($content === false) {
            ConsoleOutput::error("Failed to read file: {$file}");
            return 1;
        }

        // Determine if binary or text
        $isBinary = str_starts_with($content, ModuleDecoder::WASM_MAGIC);
        $module = $isBinary ? ModuleDecoder::decode($content) : WatCompiler::compile($content);

        $vm = new Vm();
        $store = new Store($vm);
        $wasiContext = new WasiContext(array_merge([$file], $programArgs), $_ENV);
        WasiSnapshotPreview1::bind($store, $wasiContext);

        $profiler = null;
        if ($profile) {
            $profiler = new Profiler();
            $profiler->attach($vm);
        }

        try {
            $instance = $store->instantiate($module);

            if ($invokeFunc !== null) {
                $ret = $instance->invoke($invokeFunc, $programArgs);
                if ($ret !== null) {
                    echo is_array($ret) ? implode(' ', $ret) . "\n" : (string)$ret . "\n";
                }
            } elseif ($instance->getExport('_start') !== null) {
                $instance->invoke('_start');
            } elseif ($instance->getExport('main') !== null) {
                $ret = $instance->invoke('main', $programArgs);
                if ($ret !== null) {
                    echo is_array($ret) ? implode(' ', $ret) . "\n" : (string)$ret . "\n";
                }
            } else {
                ConsoleOutput::warning('No _start or main function exported. Module instantiated successfully.');
            }

            if ($profiler !== null) {
                $profiler->detach($vm);
                echo "\n" . $profiler->renderReport();
            }

            return 0;
        } catch (WasiExitException $e) {
            if ($profiler !== null) {
                $profiler->detach($vm);
                echo "\n" . $profiler->renderReport();
            }
            return $e->exitCode;
        } catch (\Throwable $e) {
            ConsoleOutput::error('Execution error: ' . $e->getMessage());
            return 1;
        }
    }
}
