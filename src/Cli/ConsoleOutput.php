<?php

declare(strict_types=1);

namespace Zenith\Cli;

use Zenith\Debugger\Terminal;

/**
 * Formatted console output with ANSI color support, tables, and banners.
 */
final class ConsoleOutput
{
    public static function banner(): void
    {
        $ascii = <<<BANNER
  ______           _ _   _     
 |___  /          (_) | | |    
    / / ___ _ __  _ _| |_| |__  
   / / / _ \ '_ \| | | __| '_ \ 
  / /_|  __/ | | | | | |_| | | |
 /_____\___|_| |_|_|_|\__|_| |_|
BANNER;

        echo Terminal::color($ascii, Terminal::BRIGHT_CYAN . Terminal::BOLD) . "\n";
        echo Terminal::color("  WebAssembly 2.0 Virtual Machine & Toolchain in Pure PHP", Terminal::DIM . Terminal::WHITE) . "\n\n";
    }

    public static function info(string $msg): void
    {
        echo Terminal::color('[INFO] ', Terminal::BRIGHT_BLUE) . $msg . "\n";
    }

    public static function success(string $msg): void
    {
        echo Terminal::color('[SUCCESS] ', Terminal::BRIGHT_GREEN) . $msg . "\n";
    }

    public static function warning(string $msg): void
    {
        echo Terminal::color('[WARNING] ', Terminal::BRIGHT_YELLOW) . $msg . "\n";
    }

    public static function error(string $msg): void
    {
        echo Terminal::color('[ERROR] ', Terminal::BRIGHT_RED . Terminal::BOLD) . $msg . "\n";
    }

    public static function header(string $title): void
    {
        echo "\n" . Terminal::color("=== {$title} ===", Terminal::BOLD . Terminal::WHITE) . "\n";
    }
}
