<?php

declare(strict_types=1);

namespace Zenith\Binary;

use RuntimeException;
use Zenith\Ast\CustomSection;
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
use Zenith\Ast\MemoryType;
use Zenith\Ast\Module;
use Zenith\Ast\Table;
use Zenith\Ast\TableType;
use Zenith\Ast\ValueType;

/**
 * WebAssembly binary module decoder.
 *
 * Parses raw .wasm binary data into an in-memory Module AST according
 * to the WebAssembly 2.0 binary format specification.
 */
final class ModuleDecoder
{
    public const WASM_MAGIC = "\0asm";
    public const WASM_VERSION = 1;

    public static function decode(string $binary): Module
    {
        $reader = new BinaryReader($binary);

        $magic = $reader->readBytes(4);
        if ($magic !== self::WASM_MAGIC) {
            throw new RuntimeException(sprintf('Invalid WebAssembly magic header: expected \\0asm, got %s', bin2hex($magic)));
        }

        $version = $reader->readFixedUint32();
        if ($version !== self::WASM_VERSION) {
            throw new RuntimeException(sprintf('Unsupported WebAssembly binary version: %d (expected 1)', $version));
        }

        $module = new Module($version);
        $funcTypeIndices = [];

        while (!$reader->isEof()) {
            $sectionId = $reader->readByte();
            $sectionLength = $reader->readVarUint32();
            $sectionReader = $reader->subReader($sectionLength);

            match ($sectionId) {
                SectionType::Custom->value => self::decodeCustomSection($sectionReader, $module),
                SectionType::Type->value => self::decodeTypeSection($sectionReader, $module),
                SectionType::Import->value => self::decodeImportSection($sectionReader, $module),
                SectionType::Function->value => $funcTypeIndices = self::decodeFunctionSection($sectionReader),
                SectionType::Table->value => self::decodeTableSection($sectionReader, $module),
                SectionType::Memory->value => self::decodeMemorySection($sectionReader, $module),
                SectionType::Global->value => self::decodeGlobalSection($sectionReader, $module),
                SectionType::Export->value => self::decodeExportSection($sectionReader, $module),
                SectionType::Start->value => self::decodeStartSection($sectionReader, $module),
                SectionType::Element->value => self::decodeElementSection($sectionReader, $module),
                SectionType::Code->value => self::decodeCodeSection($sectionReader, $module, $funcTypeIndices),
                SectionType::Data->value => self::decodeDataSection($sectionReader, $module),
                SectionType::DataCount->value => null, // Informational count in Wasm 2.0
                default => throw new RuntimeException(sprintf('Unknown section ID: 0x%02X', $sectionId)),
            };
        }

        self::applyNameSection($module);

        return $module;
    }

    private static function decodeCustomSection(BinaryReader $reader, Module $module): void
    {
        $name = $reader->readName();
        $data = $reader->readBytes($reader->getRemaining());
        $module->customSections[] = new CustomSection($name, $data);
    }

    private static function decodeTypeSection(BinaryReader $reader, Module $module): void
    {
        $module->types = $reader->readVector(static function (BinaryReader $r): FuncType {
            $form = $r->readByte();
            if ($form !== 0x60) {
                throw new RuntimeException(sprintf('Invalid function type form: expected 0x60, got 0x%02X', $form));
            }

            $params = $r->readVector(static fn(BinaryReader $sub): ValueType => $sub->readValueType());
            $results = $r->readVector(static fn(BinaryReader $sub): ValueType => $sub->readValueType());

            return new FuncType($params, $results);
        });
    }

    private static function decodeImportSection(BinaryReader $reader, Module $module): void
    {
        $module->imports = $reader->readVector(static function (BinaryReader $r): Import {
            $modName = $r->readName();
            $field = $r->readName();
            $kindByte = $r->readByte();

            $kind = ExternalKind::tryFrom($kindByte) ?? throw new RuntimeException("Unknown import kind: {$kindByte}");

            $desc = match ($kind) {
                ExternalKind::Function => $r->readVarUint32(),
                ExternalKind::Table => new TableType($r->readValueType(), $r->readLimits()),
                ExternalKind::Memory => new MemoryType($r->readLimits()),
                ExternalKind::Global => new GlobalType($r->readValueType(), $r->readByte() === 1),
            };

            return new Import($modName, $field, $kind, $desc);
        });
    }

    /**
     * @return list<int>
     */
    private static function decodeFunctionSection(BinaryReader $reader): array
    {
        return $reader->readVector(static fn(BinaryReader $r): int => $r->readVarUint32());
    }

    private static function decodeTableSection(BinaryReader $reader, Module $module): void
    {
        $module->tables = $reader->readVector(static function (BinaryReader $r): Table {
            $elemType = $r->readValueType();
            $limits = $r->readLimits();
            return new Table(new TableType($elemType, $limits));
        });
    }

    private static function decodeMemorySection(BinaryReader $reader, Module $module): void
    {
        $module->memories = $reader->readVector(static function (BinaryReader $r): Memory {
            $limits = $r->readLimits();
            return new Memory(new MemoryType($limits));
        });
    }

    private static function decodeGlobalSection(BinaryReader $reader, Module $module): void
    {
        $module->globals = $reader->readVector(static function (BinaryReader $r): GlobalVar {
            $valType = $r->readValueType();
            $mutable = $r->readByte() === 1;
            $initExpr = $r->readExpression();

            return new GlobalVar(new GlobalType($valType, $mutable), $initExpr);
        });
    }

    private static function decodeExportSection(BinaryReader $reader, Module $module): void
    {
        $module->exports = $reader->readVector(static function (BinaryReader $r): Export {
            $name = $r->readName();
            $kindByte = $r->readByte();
            $kind = ExternalKind::tryFrom($kindByte) ?? throw new RuntimeException("Unknown export kind: {$kindByte}");
            $index = $r->readVarUint32();

            return new Export($name, $kind, $index);
        });
    }

    private static function decodeStartSection(BinaryReader $reader, Module $module): void
    {
        $module->start = $reader->readVarUint32();
    }

    private static function decodeElementSection(BinaryReader $reader, Module $module): void
    {
        $module->elements = $reader->readVector(static function (BinaryReader $r): ElementSegment {
            $flags = $r->readVarUint32();

            if ($flags === 0) {
                // Active segment for table 0
                $offsetExpr = $r->readExpression();
                $elements = $r->readVector(static fn(BinaryReader $sub): int => $sub->readVarUint32());
                return new ElementSegment(ElementSegment::MODE_ACTIVE, 0, $offsetExpr, ValueType::FuncRef, $elements);
            }

            if ($flags === 1) {
                // Passive segment
                $elemKind = $r->readByte(); // 0x00
                $elements = $r->readVector(static fn(BinaryReader $sub): int => $sub->readVarUint32());
                return new ElementSegment(ElementSegment::MODE_PASSIVE, 0, [], ValueType::FuncRef, $elements);
            }

            if ($flags === 2) {
                // Active segment with table index
                $tableIndex = $r->readVarUint32();
                $offsetExpr = $r->readExpression();
                $elemKind = $r->readByte();
                $elements = $r->readVector(static fn(BinaryReader $sub): int => $sub->readVarUint32());
                return new ElementSegment(ElementSegment::MODE_ACTIVE, $tableIndex, $offsetExpr, ValueType::FuncRef, $elements);
            }

            if ($flags === 3) {
                // Declarative segment
                $elemKind = $r->readByte();
                $elements = $r->readVector(static fn(BinaryReader $sub): int => $sub->readVarUint32());
                return new ElementSegment(ElementSegment::MODE_DECLARATIVE, 0, [], ValueType::FuncRef, $elements);
            }

            throw new RuntimeException("Unsupported element segment flag: {$flags}");
        });
    }

    /**
     * @param list<int> $funcTypeIndices
     */
    private static function decodeCodeSection(BinaryReader $reader, Module $module, array $funcTypeIndices): void
    {
        $codeBodies = $reader->readVector(static function (BinaryReader $r): array {
            $bodySize = $r->readVarUint32();
            $bodyReader = $r->subReader($bodySize);

            // Locals vector: each entry is (count: u32, type: ValueType)
            $localEntries = $bodyReader->readVector(static function (BinaryReader $sub): array {
                $count = $sub->readVarUint32();
                $type = $sub->readValueType();
                return ['count' => $count, 'type' => $type];
            });

            $locals = [];
            foreach ($localEntries as $entry) {
                for ($i = 0; $i < $entry['count']; $i++) {
                    $locals[] = $entry['type'];
                }
            }

            $instructions = [];
            while (!$bodyReader->isEof()) {
                $instructions[] = $bodyReader->readInstruction();
            }

            return [
                'locals' => $locals,
                'instructions' => $instructions,
            ];
        });

        if (count($codeBodies) !== count($funcTypeIndices)) {
            throw new RuntimeException(sprintf(
                'Mismatch between declared functions (%d) and code bodies (%d)',
                count($funcTypeIndices),
                count($codeBodies)
            ));
        }

        foreach ($codeBodies as $i => $body) {
            $typeIdx = $funcTypeIndices[$i];
            $module->functions[] = new FunctionDef(
                $typeIdx,
                $body['locals'],
                $body['instructions']
            );
        }
    }

    private static function decodeDataSection(BinaryReader $reader, Module $module): void
    {
        $module->data = $reader->readVector(static function (BinaryReader $r): DataSegment {
            $flags = $r->readVarUint32();

            if ($flags === 0) {
                // Active data segment for memory 0
                $offsetExpr = $r->readExpression();
                $len = $r->readVarUint32();
                $bytes = $r->readBytes($len);
                return new DataSegment(DataSegment::MODE_ACTIVE, 0, $offsetExpr, $bytes);
            }

            if ($flags === 1) {
                // Passive data segment
                $len = $r->readVarUint32();
                $bytes = $r->readBytes($len);
                return new DataSegment(DataSegment::MODE_PASSIVE, 0, [], $bytes);
            }

            if ($flags === 2) {
                // Active data segment with explicit memory index
                $memIdx = $r->readVarUint32();
                $offsetExpr = $r->readExpression();
                $len = $r->readVarUint32();
                $bytes = $r->readBytes($len);
                return new DataSegment(DataSegment::MODE_ACTIVE, $memIdx, $offsetExpr, $bytes);
            }

            throw new RuntimeException("Unsupported data segment flag: {$flags}");
        });
    }

    private static function applyNameSection(Module $module): void
    {
        foreach ($module->customSections as $custom) {
            if ($custom->name !== 'name') {
                continue;
            }

            try {
                $reader = new BinaryReader($custom->data);
                while (!$reader->isEof()) {
                    $subId = $reader->readByte();
                    $subLen = $reader->readVarUint32();
                    $subReader = $reader->subReader($subLen);

                    if ($subId === 1) {
                        // Function names
                        $nameMap = $subReader->readVector(static function (BinaryReader $r): array {
                            $idx = $r->readVarUint32();
                            $name = $r->readName();
                            return ['index' => $idx, 'name' => $name];
                        });

                        foreach ($nameMap as $entry) {
                            $func = $module->getFunction($entry['index']);
                            if ($func !== null) {
                                $func->debugName = $entry['name'];
                            }
                        }
                    } elseif ($subId === 2) {
                        // Local names
                        $localMap = $subReader->readVector(static function (BinaryReader $r): array {
                            $funcIdx = $r->readVarUint32();
                            $locals = $r->readVector(static function (BinaryReader $sub): array {
                                $localIdx = $sub->readVarUint32();
                                $name = $sub->readName();
                                return ['index' => $localIdx, 'name' => $name];
                            });
                            return ['funcIndex' => $funcIdx, 'locals' => $locals];
                        });

                        foreach ($localMap as $entry) {
                            $func = $module->getFunction($entry['funcIndex']);
                            if ($func !== null) {
                                foreach ($entry['locals'] as $l) {
                                    $func->localNames[$l['index']] = $l['name'];
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                // Name section is non-critical debug metadata; ignore malformed debug sections
            }
        }
    }
}
