<?php

declare(strict_types=1);

namespace Zenith\Binary;

use RuntimeException;
use Zenith\Ast\BlockType;
use Zenith\Ast\Instruction;
use Zenith\Ast\Limits;
use Zenith\Ast\ValueType;

/**
 * Binary stream reader for WebAssembly bytecode and structured data.
 */
final class BinaryReader
{
    private int $offset = 0;
    private int $length;

    public function __construct(
        private readonly string $buffer,
        int $offset = 0,
        ?int $length = null,
    ) {
        $this->offset = $offset;
        $this->length = $length ?? strlen($buffer);
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): void
    {
        $this->offset = $offset;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getRemaining(): int
    {
        return max(0, $this->length - $this->offset);
    }

    public function isEof(): bool
    {
        return $this->offset >= $this->length;
    }

    public function readByte(): int
    {
        if ($this->offset >= $this->length) {
            throw new RuntimeException('Unexpected end of binary stream while reading byte');
        }
        $byte = ord($this->buffer[$this->offset]);
        $this->offset++;
        return $byte;
    }

    public function peekByte(): int
    {
        if ($this->offset >= $this->length) {
            throw new RuntimeException('Unexpected end of binary stream while peeking byte');
        }
        return ord($this->buffer[$this->offset]);
    }

    public function readBytes(int $count): string
    {
        if ($this->offset + $count > $this->length) {
            throw new RuntimeException("Unexpected end of stream: requested {$count} bytes, {$this->getRemaining()} available");
        }
        $bytes = substr($this->buffer, $this->offset, $count);
        $this->offset += $count;
        return $bytes;
    }

    public function readVarUint32(): int
    {
        return Leb128::decodeUint32($this);
    }

    public function readVarInt32(): int
    {
        return Leb128::decodeInt32($this);
    }

    public function readVarUint64(): int
    {
        return Leb128::decodeUint64($this);
    }

    public function readVarInt64(): int
    {
        return Leb128::decodeInt64($this);
    }

    public function readFixedUint32(): int
    {
        $bytes = $this->readBytes(4);
        return unpack('V', $bytes)[1];
    }

    public function readFixedUint64(): int
    {
        $bytes = $this->readBytes(8);
        return unpack('P', $bytes)[1];
    }

    public function readFloat32(): float
    {
        $bytes = $this->readBytes(4);
        return unpack('f', $bytes)[1];
    }

    public function readFloat64(): float
    {
        $bytes = $this->readBytes(8);
        return unpack('d', $bytes)[1];
    }

    public function readName(): string
    {
        $length = $this->readVarUint32();
        return $this->readBytes($length);
    }

    public function readLimits(): Limits
    {
        $flag = $this->readByte();
        $min = $this->readVarUint32();
        $max = null;

        if ($flag === 1) {
            $max = $this->readVarUint32();
        }

        return new Limits($min, $max);
    }

    public function readValueType(): ValueType
    {
        $byte = $this->readByte();
        $type = ValueType::tryFromByte($byte);
        if ($type === null) {
            throw new RuntimeException(sprintf('Unknown value type byte: 0x%02X', $byte));
        }
        return $type;
    }

    public function readBlockType(): BlockType
    {
        $byte = $this->peekByte();
        if ($byte === 0x40) {
            $this->readByte();
            return BlockType::empty();
        }

        $type = ValueType::tryFromByte($byte);
        if ($type !== null) {
            $this->readByte();
            return BlockType::fromValueType($type);
        }

        $typeIdx = $this->readVarInt32();
        return BlockType::fromTypeIndex($typeIdx);
    }

    /**
     * @template T
     * @param callable(self): T $fn
     * @return list<T>
     */
    public function readVector(callable $fn): array
    {
        $count = $this->readVarUint32();
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = $fn($this);
        }
        return $items;
    }

    public function subReader(int $length): self
    {
        if ($this->offset + $length > $this->length) {
            throw new RuntimeException("Subreader exceeds remaining bytes: {$length} requested, {$this->getRemaining()} remaining");
        }
        $sub = new self($this->buffer, $this->offset, $this->offset + $length);
        $this->offset += $length;
        return $sub;
    }

    public function readInstruction(): Instruction
    {
        $insOffset = $this->offset;
        $opcode = $this->readByte();

        if ($opcode === Opcode::PREFIX_MISC) {
            $subOpcode = $this->readVarUint32();
            $opcode = ($opcode << 8) | $subOpcode;
        }

        $immediate = match ($opcode) {
            Opcode::BLOCK, Opcode::LOOP, Opcode::IF => $this->readBlockType(),
            Opcode::BR, Opcode::BR_IF => $this->readVarUint32(),
            Opcode::BR_TABLE => [
                'targets' => $this->readVector(static fn(BinaryReader $r): int => $r->readVarUint32()),
                'default' => $this->readVarUint32(),
            ],
            Opcode::CALL => $this->readVarUint32(),
            Opcode::CALL_INDIRECT => [
                'typeIndex' => $this->readVarUint32(),
                'tableIndex' => $this->readVarUint32(),
            ],
            Opcode::SELECT_T => $this->readVector(static fn(BinaryReader $r): ValueType => $r->readValueType()),
            Opcode::LOCAL_GET, Opcode::LOCAL_SET, Opcode::LOCAL_TEE => $this->readVarUint32(),
            Opcode::GLOBAL_GET, Opcode::GLOBAL_SET => $this->readVarUint32(),
            Opcode::TABLE_GET, Opcode::TABLE_SET => $this->readVarUint32(),
            Opcode::I32_LOAD, Opcode::I64_LOAD, Opcode::F32_LOAD, Opcode::F64_LOAD,
            Opcode::I32_LOAD8_S, Opcode::I32_LOAD8_U, Opcode::I32_LOAD16_S, Opcode::I32_LOAD16_U,
            Opcode::I64_LOAD8_S, Opcode::I64_LOAD8_U, Opcode::I64_LOAD16_S, Opcode::I64_LOAD16_U,
            Opcode::I64_LOAD32_S, Opcode::I64_LOAD32_U,
            Opcode::I32_STORE, Opcode::I64_STORE, Opcode::F32_STORE, Opcode::F64_STORE,
            Opcode::I32_STORE8, Opcode::I32_STORE16, Opcode::I64_STORE8, Opcode::I64_STORE16, Opcode::I64_STORE32 => [
                'align' => $this->readVarUint32(),
                'offset' => $this->readVarUint32(),
            ],
            Opcode::MEMORY_SIZE, Opcode::MEMORY_GROW => $this->readByte(), // 0x00 reserved byte
            Opcode::I32_CONST => $this->readVarInt32(),
            Opcode::I64_CONST => $this->readVarInt64(),
            Opcode::F32_CONST => $this->readFloat32(),
            Opcode::F64_CONST => $this->readFloat64(),
            Opcode::REF_NULL => $this->readValueType(),
            Opcode::REF_FUNC => $this->readVarUint32(),
            Opcode::MEMORY_INIT => [
                'dataIndex' => $this->readVarUint32(),
                'memoryIndex' => $this->readByte(),
            ],
            Opcode::DATA_DROP => $this->readVarUint32(),
            Opcode::MEMORY_COPY => [
                'srcMem' => $this->readByte(),
                'dstMem' => $this->readByte(),
            ],
            Opcode::MEMORY_FILL => $this->readByte(),
            Opcode::TABLE_INIT => [
                'elemIndex' => $this->readVarUint32(),
                'tableIndex' => $this->readVarUint32(),
            ],
            Opcode::ELEM_DROP => $this->readVarUint32(),
            Opcode::TABLE_COPY => [
                'dstTable' => $this->readVarUint32(),
                'srcTable' => $this->readVarUint32(),
            ],
            Opcode::TABLE_GROW, Opcode::TABLE_SIZE, Opcode::TABLE_FILL => $this->readVarUint32(),
            default => null,
        };

        return new Instruction($opcode, $immediate, $insOffset);
    }

    /**
     * @return list<Instruction>
     */
    public function readExpression(): array
    {
        $instructions = [];
        $depth = 1;
        while (!$this->isEof()) {
            $ins = $this->readInstruction();
            $instructions[] = $ins;
            if ($ins->is(Opcode::BLOCK) || $ins->is(Opcode::LOOP) || $ins->is(Opcode::IF)) {
                $depth++;
            } elseif ($ins->is(Opcode::END)) {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
        }
        return $instructions;
    }
}
