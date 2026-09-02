<?php

declare(strict_types=1);

namespace Zenith\Runtime;

use RuntimeException;
use Zenith\Ast\Export;
use Zenith\Ast\ExternalKind;
use Zenith\Ast\FuncType;
use Zenith\Ast\Module;

/**
 * An instantiated WebAssembly module bound to runtime resources.
 */
final class Instance
{
    /** @var list<FuncType> */
    public array $types = [];
    /** @var list<WasmFunction|HostFunction> */
    public array $functions = [];
    /** @var list<Table> */
    public array $tables = [];
    /** @var list<Memory> */
    public array $memories = [];
    /** @var list<GlobalInstance> */
    public array $globals = [];
    /** @var array<string, Export> */
    public array $exports = [];

    public function __construct(
        public readonly Module $module,
        public readonly Store $store,
    ) {
    }

    public function getExport(string $name): ?Export
    {
        return $this->exports[$name] ?? null;
    }

    public function getFunction(int $index): WasmFunction|HostFunction
    {
        if (!isset($this->functions[$index])) {
            throw new RuntimeException("Function index {$index} out of bounds");
        }
        return $this->functions[$index];
    }

    public function getMemory(int $index = 0): ?Memory
    {
        return $this->memories[$index] ?? null;
    }

    public function getTable(int $index = 0): ?Table
    {
        return $this->tables[$index] ?? null;
    }

    public function getGlobal(int $index): ?GlobalInstance
    {
        return $this->globals[$index] ?? null;
    }

    /**
     * Invoke an exported function by name.
     *
     * @param string $name Exported function identifier
     * @param list<mixed> $args Raw arguments
     * @return mixed Single value or array of values
     */
    public function invoke(string $name, array $args = []): mixed
    {
        $export = $this->getExport($name);
        if ($export === null || $export->kind !== ExternalKind::Function) {
            throw new RuntimeException("Exported function '{$name}' not found");
        }

        $func = $this->getFunction($export->index);
        return $this->store->getVm()->callFunction($func, $args, $this);
    }
}
