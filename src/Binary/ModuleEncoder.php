<?php

declare(strict_types=1);

namespace Zenith\Binary;

use Zenith\Ast\DataSegment;
use Zenith\Ast\ElementSegment;
use Zenith\Ast\Export;
use Zenith\Ast\ExternalKind;
use Zenith\Ast\FunctionDef;
use Zenith\Ast\FuncType;
use Zenith\Ast\GlobalType;
use Zenith\Ast\GlobalVar;
use Zenith\Ast\Import;
use Zenith\Ast\Memory;
use Zenith\Ast\Module;
use Zenith\Ast\Table;
use Zenith\Ast\ValueType;

/**
 * WebAssembly binary module encoder.
 *
 * Serializes an in-memory Module AST into standard .wasm binary representation.
 */
final class ModuleEncoder
{
    public static function encode(Module $module): string
    {
        $out = new BinaryWriter();

        // Magic and version
        $out->writeBytes(ModuleDecoder::WASM_MAGIC);
        $out->writeFixedUint32($module->version);

        // Section 1: Type
        if (!empty($module->types)) {
            $sec = new BinaryWriter();
            $sec->writeVector($module->types, static function (BinaryWriter $w, FuncType $type): void {
                $w->writeByte(0x60);
                $w->writeVector($type->params, static fn(BinaryWriter $sub, ValueType $t) => $sub->writeValueType($t));
                $w->writeVector($type->results, static fn(BinaryWriter $sub, ValueType $t) => $sub->writeValueType($t));
            });
            self::writeSection($out, SectionType::Type, $sec->getBuffer());
        }

        // Section 2: Import
        if (!empty($module->imports)) {
            $sec = new BinaryWriter();
            $sec->writeVector($module->imports, static function (BinaryWriter $w, Import $imp): void {
                $w->writeName($imp->module);
                $w->writeName($imp->field);
                $w->writeByte($imp->kind->value);
                match ($imp->kind) {
                    ExternalKind::Function => $w->writeVarUint32($imp->getTypeIndex()),
                    ExternalKind::Table => (function () use ($w, $imp) {
                        $w->writeValueType($imp->desc->elemType);
                        $w->writeLimits($imp->desc->limits);
                    })(),
                    ExternalKind::Memory => $w->writeLimits($imp->desc->limits),
                    ExternalKind::Global => (function () use ($w, $imp) {
                        $w->writeValueType($imp->desc->valType);
                        $w->writeByte($imp->desc->mutable ? 1 : 0);
                    })(),
                };
            });
            self::writeSection($out, SectionType::Import, $sec->getBuffer());
        }

        // Section 3: Function
        if (!empty($module->functions)) {
            $sec = new BinaryWriter();
            $sec->writeVector($module->functions, static function (BinaryWriter $w, FunctionDef $f): void {
                $w->writeVarUint32($f->typeIndex);
            });
            self::writeSection($out, SectionType::Function, $sec->getBuffer());
        }

        // Section 4: Table
        if (!empty($module->tables)) {
            $sec = new BinaryWriter();
            $sec->writeVector($module->tables, static function (BinaryWriter $w, Table $t): void {
                $w->writeValueType($t->type->elemType);
                $w->writeLimits($t->type->limits);
            });
            self::writeSection($out, SectionType::Table, $sec->getBuffer());
        }

        // Section 5: Memory
        if (!empty($module->memories)) {
            $sec = new BinaryWriter();
            $sec->writeVector($module->memories, static function (BinaryWriter $w, Memory $m): void {
                $w->writeLimits($m->type->limits);
            });
            self::writeSection($out, SectionType::Memory, $sec->getBuffer());
        }

        // Section 6: Global
        if (!empty($module->globals)) {
            $sec = new BinaryWriter();
            $sec->writeVector($module->globals, static function (BinaryWriter $w, GlobalVar $g): void {
                $w->writeValueType($g->type->valType);
                $w->writeByte($g->type->mutable ? 1 : 0);
                $w->writeExpression($g->initExpr);
            });
            self::writeSection($out, SectionType::Global, $sec->getBuffer());
        }

        // Section 7: Export
        if (!empty($module->exports)) {
            $sec = new BinaryWriter();
            $sec->writeVector($module->exports, static function (BinaryWriter $w, Export $e): void {
                $w->writeName($e->name);
                $w->writeByte($e->kind->value);
                $w->writeVarUint32($e->index);
            });
            self::writeSection($out, SectionType::Export, $sec->getBuffer());
        }

        // Section 8: Start
        if ($module->start !== null) {
            $sec = new BinaryWriter();
            $sec->writeVarUint32($module->start);
            self::writeSection($out, SectionType::Start, $sec->getBuffer());
        }

        // Section 9: Element
        if (!empty($module->elements)) {
            $sec = new BinaryWriter();
            $sec->writeVector($module->elements, static function (BinaryWriter $w, ElementSegment $e): void {
                if ($e->isActive() && $e->tableIndex === 0) {
                    $w->writeVarUint32(0);
                    $w->writeExpression($e->offsetExpr);
                    $w->writeVector($e->elements, static fn(BinaryWriter $sub, int $idx) => $sub->writeVarUint32($idx));
                } else {
                    $w->writeVarUint32(2);
                    $w->writeVarUint32($e->tableIndex);
                    $w->writeExpression($e->offsetExpr);
                    $w->writeByte(0x00);
                    $w->writeVector($e->elements, static fn(BinaryWriter $sub, int $idx) => $sub->writeVarUint32($idx));
                }
            });
            self::writeSection($out, SectionType::Element, $sec->getBuffer());
        }

        // Section 10: Code
        if (!empty($module->functions)) {
            $sec = new BinaryWriter();
            $sec->writeVector($module->functions, static function (BinaryWriter $w, FunctionDef $f): void {
                $body = new BinaryWriter();

                // Compress locals run-length (e.g. i32, i32, f32 -> (2, i32), (1, f32))
                $runs = [];
                $currentType = null;
                $currentCount = 0;

                foreach ($f->locals as $local) {
                    if ($currentType === null) {
                        $currentType = $local;
                        $currentCount = 1;
                    } elseif ($currentType === $local) {
                        $currentCount++;
                    } else {
                        $runs[] = ['count' => $currentCount, 'type' => $currentType];
                        $currentType = $local;
                        $currentCount = 1;
                    }
                }
                if ($currentCount > 0 && $currentType !== null) {
                    $runs[] = ['count' => $currentCount, 'type' => $currentType];
                }

                $body->writeVector($runs, static function (BinaryWriter $sub, array $run): void {
                    $sub->writeVarUint32($run['count']);
                    $sub->writeValueType($run['type']);
                });

                $body->writeExpression($f->body);

                $w->writeVarUint32($body->getLength());
                $w->writeBytes($body->getBuffer());
            });
            self::writeSection($out, SectionType::Code, $sec->getBuffer());
        }

        // Section 11: Data
        if (!empty($module->data)) {
            $sec = new BinaryWriter();
            $sec->writeVector($module->data, static function (BinaryWriter $w, DataSegment $d): void {
                if ($d->isActive() && $d->memoryIndex === 0) {
                    $w->writeVarUint32(0);
                    $w->writeExpression($d->offsetExpr);
                    $w->writeVarUint32(strlen($d->data));
                    $w->writeBytes($d->data);
                } else {
                    $w->writeVarUint32(2);
                    $w->writeVarUint32($d->memoryIndex);
                    $w->writeExpression($d->offsetExpr);
                    $w->writeVarUint32(strlen($d->data));
                    $w->writeBytes($d->data);
                }
            });
            self::writeSection($out, SectionType::Data, $sec->getBuffer());
        }

        // Name custom section if debug symbols exist
        $nameSec = self::buildNameSection($module);
        if ($nameSec !== null) {
            self::writeSection($out, SectionType::Custom, $nameSec);
        }

        return $out->getBuffer();
    }

    private static function writeSection(BinaryWriter $out, SectionType $type, string $payload): void
    {
        $out->writeByte($type->value);
        $out->writeVarUint32(strlen($payload));
        $out->writeBytes($payload);
    }

    private static function buildNameSection(Module $module): ?string
    {
        $funcNames = [];
        $importedCount = $module->getImportedFunctionCount();

        foreach ($module->functions as $i => $func) {
            if ($func->debugName !== null && $func->debugName !== '') {
                $funcNames[] = ['index' => $importedCount + $i, 'name' => $func->debugName];
            }
        }

        if (empty($funcNames)) {
            return null;
        }

        $w = new BinaryWriter();
        $w->writeName('name');

        // Subsection 1: function names
        $sub = new BinaryWriter();
        $sub->writeVector($funcNames, static function (BinaryWriter $sw, array $entry): void {
            $sw->writeVarUint32($entry['index']);
            $sw->writeName($entry['name']);
        });

        $w->writeByte(1);
        $w->writeVarUint32($sub->getLength());
        $w->writeBytes($sub->getBuffer());

        return $w->getBuffer();
    }
}
