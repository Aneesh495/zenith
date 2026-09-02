<?php

declare(strict_types=1);

namespace Zenith\Binary;

use Zenith\Ast\BlockType;
use Zenith\Ast\Instruction;
use Zenith\Ast\Limits;
use Zenith\Ast\ValueType;

/**
 * Binary stream writer for serializing WebAssembly bytecode and structured sections.
 */
final class BinaryWriter
{
    private string $buffer = '';

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    public function getLength(): int
    {
        return strlen($this->buffer);
    }

    public function writeByte(int $byte): self
    {
        $this->buffer .= chr($byte & 0xFF);
        return $this;
    }

    public function writeBytes(string $bytes): self
    {
        $this->buffer .= $bytes;
        return $this;
    }

    public function writeVarUint32(int $val): self
    {
        $this->buffer .= Leb128::encodeUint($val);
        return $this;
    }

    public function writeVarInt32(int $val): self
    {
        $this->buffer .= Leb128::encodeInt($val);
        return $this;
    }

    public function writeVarUint64(int $val): self
    {
        $this->buffer .= Leb128::encodeUint($val);
        return $this;
    }

    public function writeVarInt64(int $val): self
    {
        $this->buffer .= Leb128::encodeInt($val);
        return $this;
    }

    public function writeFixedUint32(int $val): self
    {
        $this->buffer .= pack('V', $val);
        return $this;
    }

    public function writeFloat32(float $val): self
    {
        $this->buffer .= pack('f', $val);
        return $this;
    }

    public function writeFloat64(float $val): self
    {
        $this->buffer .= pack('d', $val);
        return $this;
    }

    public function writeName(string $name): self
    {
        $this->writeVarUint32(strlen($name));
        $this->writeBytes($name);
        return $this;
    }

    public function writeLimits(Limits $limits): self
    {
        if ($limits->max !== null) {
            $this->writeByte(0x01);
            $this->writeVarUint32($limits->min);
            $this->writeVarUint32($limits->max);
        } else {
            $this->writeByte(0x00);
            $this->writeVarUint32($limits->min);
        }
        return $this;
    }

    public function writeValueType(ValueType $type): self
    {
        $this->writeByte($type->value);
        return $this;
    }

    public function writeBlockType(BlockType $type): self
    {
        if ($type->isEmpty) {
            $this->writeByte(0x40);
        } elseif ($type->valType !== null) {
            $this->writeByte($type->valType->value);
        } elseif ($type->typeIndex !== null) {
            $this->writeVarInt32($type->typeIndex);
        }
        return $this;
    }

    /**
     * @template T
     * @param list<T> $items
     * @param callable(self, T): void $fn
     */
    public function writeVector(array $items, callable $fn): self
    {
        $this->writeVarUint32(count($items));
        foreach ($items as $item) {
            $fn($this, $item);
        }
        return $this;
    }

    public function writeInstruction(Instruction $ins): self
    {
        $opcode = $ins->opcode;

        if (($opcode >> 8) === Opcode::PREFIX_MISC) {
            $this->writeByte(Opcode::PREFIX_MISC);
            $this->writeVarUint32($opcode & 0xFF);
        } else {
            $this->writeByte($opcode);
        }

        $imm = $ins->immediate;

        match ($opcode) {
            Opcode::BLOCK, Opcode::LOOP, Opcode::IF => $this->writeBlockType($imm),
            Opcode::BR, Opcode::BR_IF => $this->writeVarUint32((int)$imm),
            Opcode::BR_TABLE => (function () use ($imm) {
                $this->writeVector($imm['targets'], static fn(BinaryWriter $w, int $t) => $w->writeVarUint32($t));
                $this->writeVarUint32((int)$imm['default']);
            })(),
            Opcode::CALL => $this->writeVarUint32((int)$imm),
            Opcode::CALL_INDIRECT => (function () use ($imm) {
                $this->writeVarUint32((int)$imm['typeIndex']);
                $this->writeVarUint32((int)$imm['tableIndex']);
            })(),
            Opcode::SELECT_T => $this->writeVector($imm, static fn(BinaryWriter $w, ValueType $t) => $w->writeValueType($t)),
            Opcode::LOCAL_GET, Opcode::LOCAL_SET, Opcode::LOCAL_TEE => $this->writeVarUint32((int)$imm),
            Opcode::GLOBAL_GET, Opcode::GLOBAL_SET => $this->writeVarUint32((int)$imm),
            Opcode::TABLE_GET, Opcode::TABLE_SET => $this->writeVarUint32((int)$imm),
            Opcode::I32_LOAD, Opcode::I64_LOAD, Opcode::F32_LOAD, Opcode::F64_LOAD,
            Opcode::I32_LOAD8_S, Opcode::I32_LOAD8_U, Opcode::I32_LOAD16_S, Opcode::I32_LOAD16_U,
            Opcode::I64_LOAD8_S, Opcode::I64_LOAD8_U, Opcode::I64_LOAD16_S, Opcode::I64_LOAD16_U,
            Opcode::I64_LOAD32_S, Opcode::I64_LOAD32_U,
            Opcode::I32_STORE, Opcode::I64_STORE, Opcode::F32_STORE, Opcode::F64_STORE,
            Opcode::I32_STORE8, Opcode::I32_STORE16, Opcode::I64_STORE8, Opcode::I64_STORE16, Opcode::I64_STORE32 => (function () use ($imm) {
                $this->writeVarUint32((int)($imm['align'] ?? 0));
                $this->writeVarUint32((int)($imm['offset'] ?? 0));
            })(),
            Opcode::MEMORY_SIZE, Opcode::MEMORY_GROW => $this->writeByte(0x00),
            Opcode::I32_CONST => $this->writeVarInt32((int)$imm),
            Opcode::I64_CONST => $this->writeVarInt64((int)$imm),
            Opcode::F32_CONST => $this->writeFloat32((float)$imm),
            Opcode::F64_CONST => $this->writeFloat64((float)$imm),
            Opcode::REF_NULL => $this->writeValueType($imm),
            Opcode::REF_FUNC => $this->writeVarUint32((int)$imm),
            default => null,
        };

        return $this;
    }

    /**
     * @param list<Instruction> $instructions
     */
    public function writeExpression(array $instructions): self
    {
        foreach ($instructions as $ins) {
            $this->writeInstruction($ins);
        }
        return $this;
    }
}
