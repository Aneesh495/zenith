<?php

declare(strict_types=1);

namespace Zenith\Debugger;

/**
 * ANSI terminal control, color rendering, and box drawing utilities.
 */
final class Terminal
{
    public const RESET = "\033[0m";
    public const BOLD = "\033[1m";
    public const DIM = "\033[2m";
    public const UNDERLINE = "\033[4m";

    // Foreground colors
    public const BLACK = "\033[30m";
    public const RED = "\033[31m";
    public const GREEN = "\033[32m";
    public const YELLOW = "\033[33m";
    public const BLUE = "\033[34m";
    public const MAGENTA = "\033[35m";
    public const CYAN = "\033[36m";
    public const WHITE = "\033[37m";

    // Bright foreground colors
    public const BRIGHT_RED = "\033[91m";
    public const BRIGHT_GREEN = "\033[92m";
    public const BRIGHT_YELLOW = "\033[93m";
    public const BRIGHT_BLUE = "\033[94m";
    public const BRIGHT_MAGENTA = "\033[95m";
    public const BRIGHT_CYAN = "\033[96m";
    public const BRIGHT_WHITE = "\033[97m";

    // Background colors
    public const BG_BLACK = "\033[40m";
    public const BG_BLUE = "\033[44m";
    public const BG_DARK_GRAY = "\033[100m";

    public static function color(string $text, string $ansi): string
    {
        return $ansi . $text . self::RESET;
    }

    public static function clearScreen(): string
    {
        return "\033[2J\033[H";
    }

    public static function clearLine(): string
    {
        return "\033[2K\r";
    }

    public static function horizontalRule(int $width = 78, string $char = '-'): string
    {
        return str_repeat($char, $width);
    }
}
