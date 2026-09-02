<?php

declare(strict_types=1);

/**
 * Zenith standalone zero-dependency autoloader.
 *
 * Provides PSR-4 compliant autoloading for Zenith namespaces
 * without requiring Composer vendor files to be pre-generated.
 */
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Zenith\\Tests\\' => __DIR__ . '/../tests/',
        'Zenith\\' => __DIR__ . '/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
