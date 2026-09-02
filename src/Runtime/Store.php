<?php

declare(strict_types=1);

namespace Zenith\Runtime;

use Closure;
use RuntimeException;
use Zenith\Ast\ElementSegment;
use Zenith\Ast\ExternalKind;
use Zenith\Ast\FuncType;
use Zenith\Ast\Module;
use Zenith\Ast\ValueType;

/**
 * WebAssembly Store holding instantiated modules, memory, tables, and host imports.
 */
final class Store
{
    /** @var array<string, array<string, mixed>> */
    private array $imports = [];
    private Vm $vm;

    public function __construct(?Vm $vm = null)
    {
        $this->vm = $vm ?? new Vm($this);
    }

    public function getVm(): Vm
    {
        return $this->vm;
    }

    public function registerHostFunction(string $module, string $field, FuncType $type, Closure $callback, ?string $debugName = null): self
    {
        $this->imports[$module][$field] = new HostFunction($type, $callback, $debugName ?? "{$module}.{$field}");
        return $this;
    }

    public function registerImport(string $module, string $field, mixed $entity): self
    {
        $this->imports[$module][$field] = $entity;
        return $this;
    }

    public function instantiate(Module $module): Instance
    {
        $instance = new Instance($module, $this);
        $instance->types = $module->types;

        // 1. Resolve imports
        foreach ($module->imports as $imp) {
            $val = $this->imports[$imp->module][$imp->field] ?? null;
            if ($val === null) {
                throw new RuntimeException("Unresolved import: {$imp->module}.{$imp->field}");
            }

            match ($imp->kind) {
                ExternalKind::Function => (function () use ($instance, $val) {
                    if (!$val instanceof HostFunction && !$val instanceof WasmFunction) {
                        throw new RuntimeException('Imported function must be HostFunction or WasmFunction');
                    }
                    $instance->functions[] = $val;
                })(),
                ExternalKind::Table => (function () use ($instance, $val) {
                    if (!$val instanceof Table) {
                        throw new RuntimeException('Imported table must be Table instance');
                    }
                    $instance->tables[] = $val;
                })(),
                ExternalKind::Memory => (function () use ($instance, $val) {
                    if (!$val instanceof Memory) {
                        throw new RuntimeException('Imported memory must be Memory instance');
                    }
                    $instance->memories[] = $val;
                })(),
                ExternalKind::Global => (function () use ($instance, $val) {
                    if (!$val instanceof GlobalInstance) {
                        throw new RuntimeException('Imported global must be GlobalInstance');
                    }
                    $instance->globals[] = $val;
                })(),
            };
        }

        // 2. Allocate module tables
        foreach ($module->tables as $tDef) {
            $instance->tables[] = new Table($tDef->type);
        }

        // 3. Allocate module memories
        foreach ($module->memories as $mDef) {
            $instance->memories[] = new Memory($mDef->type);
        }

        // 4. Allocate module globals and evaluate initializers
        foreach ($module->globals as $gDef) {
            $initVal = $this->vm->evaluateConstExpression($gDef->initExpr, $instance);
            $instance->globals[] = new GlobalInstance($gDef->type, $initVal);
        }

        // 5. Allocate defined functions
        foreach ($module->functions as $fDef) {
            $type = $module->types[$fDef->typeIndex];
            $instance->functions[] = new WasmFunction($type, $fDef, $instance);
        }

        // 6. Initialize active element segments
        foreach ($module->elements as $elem) {
            if ($elem->isActive()) {
                $table = $instance->tables[$elem->tableIndex] ?? throw new RuntimeException("Table {$elem->tableIndex} not found");
                $offsetVal = $this->vm->evaluateConstExpression($elem->offsetExpr, $instance);
                $offset = $offsetVal->asI32();

                foreach ($elem->elements as $i => $funcIdx) {
                    $table->set($offset + $i, $funcIdx);
                }
            }
        }

        // 7. Initialize active data segments
        foreach ($module->data as $data) {
            if ($data->isActive()) {
                $mem = $instance->memories[$data->memoryIndex] ?? throw new RuntimeException("Memory {$data->memoryIndex} not found");
                $offsetVal = $this->vm->evaluateConstExpression($data->offsetExpr, $instance);
                $offset = $offsetVal->asI32();
                $mem->writeBytes($offset, $data->data);
            }
        }

        // 8. Register exports
        foreach ($module->exports as $exp) {
            $instance->exports[$exp->name] = $exp;
        }

        // 9. Execute start function if present
        if ($module->start !== null) {
            $startFunc = $instance->getFunction($module->start);
            $this->vm->callFunction($startFunc, [], $instance);
        }

        return $instance;
    }
}
