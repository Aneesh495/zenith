<?php

declare(strict_types=1);

namespace Zenith\Ast;

/**
 * WebAssembly custom section (metadata, debug names, annotations).
 */
final class CustomSection
{
    public function __construct(
        public readonly string $name,
        public readonly string $data,
    ) {
    }
}
