<?php

declare(strict_types=1);

namespace Zenith\Ast;

use RuntimeException;

/**
 * Root AST representation of a parsed WebAssembly module.
 */
final class Module
{
    /**
     * @param int $version WebAssembly binary version (default 1)
     * @param list<FuncType> $types Type section signatures
     * @param list<Import> $imports Import section entries
     * @param list<FunctionDef> $functions Defined function bodies
     * @param list<Table> $tables Defined table instances
     * @param list<Memory> $memories Defined linear memory instances
     * @param list<GlobalVar> $globals Defined global variables
     * @param list<Export> $exports Export section entries
     * @param int|null $start Start function index if declared
     * @param list<ElementSegment> $elements Element segments for table initialization
     * @param list<DataSegment> $data Data segments for memory initialization
     * @param list<CustomSection> $customSections Custom metadata sections
     */
    public function __construct(
        public int $version = 1,
        public array $types = [],
        public array $imports = [],
        public array $functions = [],
        public array $tables = [],
        public array $memories = [],
        public array $globals = [],
        public array $exports = [],
        public ?int $start = null,
        public array $elements = [],
        public array $data = [],
        public array $customSections = [],
    ) {
    }

    public function getImportedFunctionCount(): int
    {
        $count = 0;
        foreach ($this->imports as $imp) {
            if ($imp->isFunction()) {
                $count++;
            }
        }
        return $count;
    }

    public function getTotalFunctionCount(): int
    {
        return $this->getImportedFunctionCount() + count($this->functions);
    }

    public function getFunctionType(int $funcIndex): FuncType
    {
        $importedFuncs = 0;
        foreach ($this->imports as $imp) {
            if ($imp->isFunction()) {
                if ($importedFuncs === $funcIndex) {
                    $typeIdx = $imp->getTypeIndex();
                    return $this->types[$typeIdx] ?? throw new RuntimeException("Missing type {$typeIdx}");
                }
                $importedFuncs++;
            }
        }

        $defIndex = $funcIndex - $importedFuncs;
        if (!isset($this->functions[$defIndex])) {
            throw new RuntimeException("Function index {$funcIndex} out of bounds");
        }

        $typeIdx = $this->functions[$defIndex]->typeIndex;
        return $this->types[$typeIdx] ?? throw new RuntimeException("Missing type {$typeIdx}");
    }

    public function getFunction(int $funcIndex): ?FunctionDef
    {
        $importedFuncCount = $this->getImportedFunctionCount();
        if ($funcIndex < $importedFuncCount) {
            return null;
        }

        $defIndex = $funcIndex - $importedFuncCount;
        return $this->functions[$defIndex] ?? null;
    }

    public function getExport(string $name): ?Export
    {
        foreach ($this->exports as $export) {
            if ($export->name === $name) {
                return $export;
            }
        }
        return null;
    }

    public function getExportedFunction(string $name): ?int
    {
        $export = $this->getExport($name);
        if ($export !== null && $export->isFunction()) {
            return $export->index;
        }
        return null;
    }

    public function getExportedMemory(string $name): ?int
    {
        $export = $this->getExport($name);
        if ($export !== null && $export->isMemory()) {
            return $export->index;
        }
        return null;
    }

    public function getImportedMemoryCount(): int
    {
        $count = 0;
        foreach ($this->imports as $imp) {
            if ($imp->kind === ExternalKind::Memory) {
                $count++;
            }
        }
        return $count;
    }

    public function getTotalMemoryCount(): int
    {
        return $this->getImportedMemoryCount() + count($this->memories);
    }

    public function getImportedTableCount(): int
    {
        $count = 0;
        foreach ($this->imports as $imp) {
            if ($imp->kind === ExternalKind::Table) {
                $count++;
            }
        }
        return $count;
    }

    public function getTotalTableCount(): int
    {
        return $this->getImportedTableCount() + count($this->tables);
    }

    public function getImportedGlobalCount(): int
    {
        $count = 0;
        foreach ($this->imports as $imp) {
            if ($imp->kind === ExternalKind::Global) {
                $count++;
            }
        }
        return $count;
    }

    public function getTotalGlobalCount(): int
    {
        return $this->getImportedGlobalCount() + count($this->globals);
    }
}
