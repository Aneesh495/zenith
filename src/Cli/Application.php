<?php

declare(strict_types=1);

namespace Zenith\Cli;

use Zenith\Cli\Command\AssembleCommand;
use Zenith\Cli\Command\BenchCommand;
use Zenith\Cli\Command\DebugCommand;
use Zenith\Cli\Command\DisasmCommand;
use Zenith\Cli\Command\InspectCommand;
use Zenith\Cli\Command\RunCommand;
use Zenith\Cli\Command\ValidateCommand;
use Zenith\Debugger\Terminal;

/**
 * Main command line application dispatcher and router.
 */
final class Application
{
    public const VERSION = '1.0.0';

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';
        $args = array_slice($argv, 2);

        return match ($command) {
            'run' => RunCommand::execute($args),
            'disasm', 'decompile' => DisasmCommand::execute($args),
            'assemble', 'asm' => AssembleCommand::execute($args),
            'validate', 'check' => ValidateCommand::execute($args),
            'inspect' => InspectCommand::execute($args),
            'debug' => DebugCommand::execute($args),
            'bench', 'benchmark' => BenchCommand::execute($args),
            'version', '--version', '-v' => $this->printVersion(),
            'help', '--help', '-h' => $this->printHelp(),
            default => (function () use ($command): int {
                ConsoleOutput::error("Unknown command '{$command}'. Run 'zenith help' for usage.");
                return 1;
            })(),
        };
    }

    private function printVersion(): int
    {
        echo sprintf("Zenith WebAssembly Virtual Machine v%s (PHP %s)\n", self::VERSION, PHP_VERSION);
        return 0;
    }

    private function printHelp(): int
    {
        ConsoleOutput::banner();
        echo Terminal::color("USAGE:\n", Terminal::BOLD . Terminal::WHITE);
        echo "  zenith <command> [options] [arguments]\n\n";

        echo Terminal::color("COMMANDS:\n", Terminal::BOLD . Terminal::WHITE);
        $commands = [
            ['run <file>', 'Execute a .wasm binary or .wat source file with WASI support'],
            ['disasm <file>', 'Disassemble bytecode to annotated assembly, WAT, or CFG DOT'],
            ['assemble <file.wat>', 'Assemble WebAssembly Text format into binary .wasm format'],
            ['validate <file>', 'Verify bytecode against the formal WebAssembly 2.0 type system'],
            ['inspect <file>', 'Display header, section statistics, imports, exports, and globals'],
            ['debug <file>', 'Launch the interactive step-through terminal debugger (TUI)'],
            ['bench', 'Execute performance benchmark suite (Fib, Sieve, MatMul, Sort, SHA256)'],
            ['version', 'Display Zenith version information'],
            ['help', 'Display this help screen'],
        ];

        foreach ($commands as [$cmd, $desc]) {
            echo sprintf("  %-24s %s\n", Terminal::color($cmd, Terminal::BRIGHT_GREEN), $desc);
        }

        echo "\n" . Terminal::color("EXAMPLES:\n", Terminal::BOLD . Terminal::WHITE);
        echo "  zenith run examples/wasi_hello.wat\n";
        echo "  zenith run program.wasm --invoke fib 20\n";
        echo "  zenith assemble program.wat -o program.wasm -O\n";
        echo "  zenith disasm program.wasm --wat\n";
        echo "  zenith disasm program.wasm --cfg > graph.dot\n";
        echo "  zenith validate program.wasm\n";
        echo "  zenith inspect program.wasm\n";
        echo "  zenith debug program.wasm --invoke main\n";
        echo "  zenith bench\n\n";

        return 0;
    }
}
