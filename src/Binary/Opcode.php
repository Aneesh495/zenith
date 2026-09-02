<?php

declare(strict_types=1);

namespace Zenith\Binary;

/**
 * Complete WebAssembly 2.0 opcode registry, mnemonic mapping, and immediate signatures.
 */
final class Opcode
{
    // Control instructions
    public const UNREACHABLE = 0x00;
    public const NOP = 0x01;
    public const BLOCK = 0x02;
    public const LOOP = 0x03;
    public const IF = 0x04;
    public const ELSE = 0x05;
    public const END = 0x0B;
    public const BR = 0x0C;
    public const BR_IF = 0x0D;
    public const BR_TABLE = 0x0E;
    public const RETURN = 0x0F;
    public const CALL = 0x10;
    public const CALL_INDIRECT = 0x11;

    // Parametric instructions
    public const DROP = 0x1A;
    public const SELECT = 0x1B;
    public const SELECT_T = 0x1C;

    // Variable instructions
    public const LOCAL_GET = 0x20;
    public const LOCAL_SET = 0x21;
    public const LOCAL_TEE = 0x22;
    public const GLOBAL_GET = 0x23;
    public const GLOBAL_SET = 0x24;

    // Table instructions
    public const TABLE_GET = 0x25;
    public const TABLE_SET = 0x26;

    // Memory load instructions
    public const I32_LOAD = 0x28;
    public const I64_LOAD = 0x29;
    public const F32_LOAD = 0x2A;
    public const F64_LOAD = 0x2B;
    public const I32_LOAD8_S = 0x2C;
    public const I32_LOAD8_U = 0x2D;
    public const I32_LOAD16_S = 0x2E;
    public const I32_LOAD16_U = 0x2F;
    public const I64_LOAD8_S = 0x30;
    public const I64_LOAD8_U = 0x31;
    public const I64_LOAD16_S = 0x32;
    public const I64_LOAD16_U = 0x33;
    public const I64_LOAD32_S = 0x34;
    public const I64_LOAD32_U = 0x35;

    // Memory store instructions
    public const I32_STORE = 0x36;
    public const I64_STORE = 0x37;
    public const F32_STORE = 0x38;
    public const F64_STORE = 0x39;
    public const I32_STORE8 = 0x3A;
    public const I32_STORE16 = 0x3B;
    public const I64_STORE8 = 0x3C;
    public const I64_STORE16 = 0x3D;
    public const I64_STORE32 = 0x3E;

    // Memory size and grow
    public const MEMORY_SIZE = 0x3F;
    public const MEMORY_GROW = 0x40;

    // Constants
    public const I32_CONST = 0x41;
    public const I64_CONST = 0x42;
    public const F32_CONST = 0x43;
    public const F64_CONST = 0x44;

    // Comparison i32
    public const I32_EQZ = 0x45;
    public const I32_EQ = 0x46;
    public const I32_NE = 0x47;
    public const I32_LT_S = 0x48;
    public const I32_LT_U = 0x49;
    public const I32_GT_S = 0x4A;
    public const I32_GT_U = 0x4B;
    public const I32_LE_S = 0x4C;
    public const I32_LE_U = 0x4D;
    public const I32_GE_S = 0x4E;
    public const I32_GE_U = 0x4F;

    // Comparison i64
    public const I64_EQZ = 0x50;
    public const I64_EQ = 0x51;
    public const I64_NE = 0x52;
    public const I64_LT_S = 0x53;
    public const I64_LT_U = 0x54;
    public const I64_GT_S = 0x55;
    public const I64_GT_U = 0x56;
    public const I64_LE_S = 0x57;
    public const I64_LE_U = 0x58;
    public const I64_GE_S = 0x59;
    public const I64_GE_U = 0x5A;

    // Comparison f32
    public const F32_EQ = 0x5B;
    public const F32_NE = 0x5C;
    public const F32_LT = 0x5D;
    public const F32_GT = 0x5E;
    public const F32_LE = 0x5F;
    public const F32_GE = 0x60;

    // Comparison f64
    public const F64_EQ = 0x61;
    public const F64_NE = 0x62;
    public const F64_LT = 0x63;
    public const F64_GT = 0x64;
    public const F64_LE = 0x65;
    public const F64_GE = 0x66;

    // Numeric i32
    public const I32_CLZ = 0x67;
    public const I32_CTZ = 0x68;
    public const I32_POPCNT = 0x69;
    public const I32_ADD = 0x6A;
    public const I32_SUB = 0x6B;
    public const I32_MUL = 0x6C;
    public const I32_DIV_S = 0x6D;
    public const I32_DIV_U = 0x6E;
    public const I32_REM_S = 0x6F;
    public const I32_REM_U = 0x70;
    public const I32_AND = 0x71;
    public const I32_OR = 0x72;
    public const I32_XOR = 0x73;
    public const I32_SHL = 0x74;
    public const I32_SHR_S = 0x75;
    public const I32_SHR_U = 0x76;
    public const I32_ROTL = 0x77;
    public const I32_ROTR = 0x78;

    // Numeric i64
    public const I64_CLZ = 0x79;
    public const I64_CTZ = 0x7A;
    public const I64_POPCNT = 0x7B;
    public const I64_ADD = 0x7C;
    public const I64_SUB = 0x7D;
    public const I64_MUL = 0x7E;
    public const I64_DIV_S = 0x7F;
    public const I64_DIV_U = 0x80;
    public const I64_REM_S = 0x81;
    public const I64_REM_U = 0x82;
    public const I64_AND = 0x83;
    public const I64_OR = 0x84;
    public const I64_XOR = 0x85;
    public const I64_SHL = 0x86;
    public const I64_SHR_S = 0x87;
    public const I64_SHR_U = 0x88;
    public const I64_ROTL = 0x89;
    public const I64_ROTR = 0x8A;

    // Numeric f32
    public const F32_ABS = 0x8B;
    public const F32_NEG = 0x8C;
    public const F32_CEIL = 0x8D;
    public const F32_FLOOR = 0x8E;
    public const F32_TRUNC = 0x8F;
    public const F32_NEAREST = 0x90;
    public const F32_SQRT = 0x91;
    public const F32_ADD = 0x92;
    public const F32_SUB = 0x93;
    public const F32_MUL = 0x94;
    public const F32_DIV = 0x95;
    public const F32_MIN = 0x96;
    public const F32_MAX = 0x97;
    public const F32_COPYSIGN = 0x98;

    // Numeric f64
    public const F64_ABS = 0x99;
    public const F64_NEG = 0x9A;
    public const F64_CEIL = 0x9B;
    public const F64_FLOOR = 0x9C;
    public const F64_TRUNC = 0x9D;
    public const F64_NEAREST = 0x9E;
    public const F64_SQRT = 0x9F;
    public const F64_ADD = 0xA0;
    public const F64_SUB = 0xA1;
    public const F64_MUL = 0xA2;
    public const F64_DIV = 0xA3;
    public const F64_MIN = 0xA4;
    public const F64_MAX = 0xA5;
    public const F64_COPYSIGN = 0xA6;

    // Conversions
    public const I32_WRAP_I64 = 0xA7;
    public const I32_TRUNC_F32_S = 0xA8;
    public const I32_TRUNC_F32_U = 0xA9;
    public const I32_TRUNC_F64_S = 0xAA;
    public const I32_TRUNC_F64_U = 0xAB;
    public const I64_EXTEND_I32_S = 0xAC;
    public const I64_EXTEND_I32_U = 0xAD;
    public const I64_TRUNC_F32_S = 0xAE;
    public const I64_TRUNC_F32_U = 0xAF;
    public const I64_TRUNC_F64_S = 0xB0;
    public const I64_TRUNC_F64_U = 0xB1;
    public const F32_CONVERT_I32_S = 0xB2;
    public const F32_CONVERT_I32_U = 0xB3;
    public const F32_CONVERT_I64_S = 0xB4;
    public const F32_CONVERT_I64_U = 0xB5;
    public const F32_DEMOTE_F64 = 0xB6;
    public const F64_CONVERT_I32_S = 0xB7;
    public const F64_CONVERT_I32_U = 0xB8;
    public const F64_CONVERT_I64_S = 0xB9;
    public const F64_CONVERT_I64_U = 0xBA;
    public const F64_PROMOTE_F32 = 0xBB;
    public const I32_REINTERPRET_F32 = 0xBC;
    public const I64_REINTERPRET_F64 = 0xBD;
    public const F32_REINTERPRET_I32 = 0xBE;
    public const F64_REINTERPRET_I64 = 0xBF;

    // Sign extension
    public const I32_EXTEND8_S = 0xC0;
    public const I32_EXTEND16_S = 0xC1;
    public const I64_EXTEND8_S = 0xC2;
    public const I64_EXTEND16_S = 0xC3;
    public const I64_EXTEND32_S = 0xC4;

    // Reference instructions
    public const REF_NULL = 0xD0;
    public const REF_IS_NULL = 0xD1;
    public const REF_FUNC = 0xD2;

    // Prefixes
    public const PREFIX_MISC = 0xFC;
    public const PREFIX_SIMD = 0xFD;

    // Miscellaneous prefix 0xFC operations
    public const I32_TRUNC_SAT_F32_S = 0xFC00;
    public const I32_TRUNC_SAT_F32_U = 0xFC01;
    public const I32_TRUNC_SAT_F64_S = 0xFC02;
    public const I32_TRUNC_SAT_F64_U = 0xFC03;
    public const I64_TRUNC_SAT_F32_S = 0xFC04;
    public const I64_TRUNC_SAT_F32_U = 0xFC05;
    public const I64_TRUNC_SAT_F64_S = 0xFC06;
    public const I64_TRUNC_SAT_F64_U = 0xFC07;
    public const MEMORY_INIT = 0xFC08;
    public const DATA_DROP = 0xFC09;
    public const MEMORY_COPY = 0xFC0A;
    public const MEMORY_FILL = 0xFC0B;
    public const TABLE_INIT = 0xFC0C;
    public const ELEM_DROP = 0xFC0D;
    public const TABLE_COPY = 0xFC0E;
    public const TABLE_GROW = 0xFC0F;
    public const TABLE_SIZE = 0xFC10;
    public const TABLE_FILL = 0xFC11;

    /**
     * Map of opcode to human readable mnemonic string.
     * @var array<int, string>
     */
    private static array $mnemonics = [
        self::UNREACHABLE => 'unreachable',
        self::NOP => 'nop',
        self::BLOCK => 'block',
        self::LOOP => 'loop',
        self::IF => 'if',
        self::ELSE => 'else',
        self::END => 'end',
        self::BR => 'br',
        self::BR_IF => 'br_if',
        self::BR_TABLE => 'br_table',
        self::RETURN => 'return',
        self::CALL => 'call',
        self::CALL_INDIRECT => 'call_indirect',

        self::DROP => 'drop',
        self::SELECT => 'select',
        self::SELECT_T => 'select',

        self::LOCAL_GET => 'local.get',
        self::LOCAL_SET => 'local.set',
        self::LOCAL_TEE => 'local.tee',
        self::GLOBAL_GET => 'global.get',
        self::GLOBAL_SET => 'global.set',

        self::TABLE_GET => 'table.get',
        self::TABLE_SET => 'table.set',

        self::I32_LOAD => 'i32.load',
        self::I64_LOAD => 'i64.load',
        self::F32_LOAD => 'f32.load',
        self::F64_LOAD => 'f64.load',
        self::I32_LOAD8_S => 'i32.load8_s',
        self::I32_LOAD8_U => 'i32.load8_u',
        self::I32_LOAD16_S => 'i32.load16_s',
        self::I32_LOAD16_U => 'i32.load16_u',
        self::I64_LOAD8_S => 'i64.load8_s',
        self::I64_LOAD8_U => 'i64.load8_u',
        self::I64_LOAD16_S => 'i64.load16_s',
        self::I64_LOAD16_U => 'i64.load16_u',
        self::I64_LOAD32_S => 'i64.load32_s',
        self::I64_LOAD32_U => 'i64.load32_u',

        self::I32_STORE => 'i32.store',
        self::I64_STORE => 'i64.store',
        self::F32_STORE => 'f32.store',
        self::F64_STORE => 'f64.store',
        self::I32_STORE8 => 'i32.store8',
        self::I32_STORE16 => 'i32.store16',
        self::I64_STORE8 => 'i64.store8',
        self::I64_STORE16 => 'i64.store16',
        self::I64_STORE32 => 'i64.store32',

        self::MEMORY_SIZE => 'memory.size',
        self::MEMORY_GROW => 'memory.grow',

        self::I32_CONST => 'i32.const',
        self::I64_CONST => 'i64.const',
        self::F32_CONST => 'f32.const',
        self::F64_CONST => 'f64.const',

        self::I32_EQZ => 'i32.eqz',
        self::I32_EQ => 'i32.eq',
        self::I32_NE => 'i32.ne',
        self::I32_LT_S => 'i32.lt_s',
        self::I32_LT_U => 'i32.lt_u',
        self::I32_GT_S => 'i32.gt_s',
        self::I32_GT_U => 'i32.gt_u',
        self::I32_LE_S => 'i32.le_s',
        self::I32_LE_U => 'i32.le_u',
        self::I32_GE_S => 'i32.ge_s',
        self::I32_GE_U => 'i32.ge_u',

        self::I64_EQZ => 'i64.eqz',
        self::I64_EQ => 'i64.eq',
        self::I64_NE => 'i64.ne',
        self::I64_LT_S => 'i64.lt_s',
        self::I64_LT_U => 'i64.lt_u',
        self::I64_GT_S => 'i64.gt_s',
        self::I64_GT_U => 'i64.gt_u',
        self::I64_LE_S => 'i64.le_s',
        self::I64_LE_U => 'i64.le_u',
        self::I64_GE_S => 'i64.ge_s',
        self::I64_GE_U => 'i64.ge_u',

        self::F32_EQ => 'f32.eq',
        self::F32_NE => 'f32.ne',
        self::F32_LT => 'f32.lt',
        self::F32_GT => 'f32.gt',
        self::F32_LE => 'f32.le',
        self::F32_GE => 'f32.ge',

        self::F64_EQ => 'f64.eq',
        self::F64_NE => 'f64.ne',
        self::F64_LT => 'f64.lt',
        self::F64_GT => 'f64.gt',
        self::F64_LE => 'f64.le',
        self::F64_GE => 'f64.ge',

        self::I32_CLZ => 'i32.clz',
        self::I32_CTZ => 'i32.ctz',
        self::I32_POPCNT => 'i32.popcnt',
        self::I32_ADD => 'i32.add',
        self::I32_SUB => 'i32.sub',
        self::I32_MUL => 'i32.mul',
        self::I32_DIV_S => 'i32.div_s',
        self::I32_DIV_U => 'i32.div_u',
        self::I32_REM_S => 'i32.rem_s',
        self::I32_REM_U => 'i32.rem_u',
        self::I32_AND => 'i32.and',
        self::I32_OR => 'i32.or',
        self::I32_XOR => 'i32.xor',
        self::I32_SHL => 'i32.shl',
        self::I32_SHR_S => 'i32.shr_s',
        self::I32_SHR_U => 'i32.shr_u',
        self::I32_ROTL => 'i32.rotl',
        self::I32_ROTR => 'i32.rotr',

        self::I64_CLZ => 'i64.clz',
        self::I64_CTZ => 'i64.ctz',
        self::I64_POPCNT => 'i64.popcnt',
        self::I64_ADD => 'i64.add',
        self::I64_SUB => 'i64.sub',
        self::I64_MUL => 'i64.mul',
        self::I64_DIV_S => 'i64.div_s',
        self::I64_DIV_U => 'i64.div_u',
        self::I64_REM_S => 'i64.rem_s',
        self::I64_REM_U => 'i64.rem_u',
        self::I64_AND => 'i64.and',
        self::I64_OR => 'i64.or',
        self::I64_XOR => 'i64.xor',
        self::I64_SHL => 'i64.shl',
        self::I64_SHR_S => 'i64.shr_s',
        self::I64_SHR_U => 'i64.shr_u',
        self::I64_ROTL => 'i64.rotl',
        self::I64_ROTR => 'i64.rotr',

        self::F32_ABS => 'f32.abs',
        self::F32_NEG => 'f32.neg',
        self::F32_CEIL => 'f32.ceil',
        self::F32_FLOOR => 'f32.floor',
        self::F32_TRUNC => 'f32.trunc',
        self::F32_NEAREST => 'f32.nearest',
        self::F32_SQRT => 'f32.sqrt',
        self::F32_ADD => 'f32.add',
        self::F32_SUB => 'f32.sub',
        self::F32_MUL => 'f32.mul',
        self::F32_DIV => 'f32.div',
        self::F32_MIN => 'f32.min',
        self::F32_MAX => 'f32.max',
        self::F32_COPYSIGN => 'f32.copysign',

        self::F64_ABS => 'f64.abs',
        self::F64_NEG => 'f64.neg',
        self::F64_CEIL => 'f64.ceil',
        self::F64_FLOOR => 'f64.floor',
        self::F64_TRUNC => 'f64.trunc',
        self::F64_NEAREST => 'f64.nearest',
        self::F64_SQRT => 'f64.sqrt',
        self::F64_ADD => 'f64.add',
        self::F64_SUB => 'f64.sub',
        self::F64_MUL => 'f64.mul',
        self::F64_DIV => 'f64.div',
        self::F64_MIN => 'f64.min',
        self::F64_MAX => 'f64.max',
        self::F64_COPYSIGN => 'f64.copysign',

        self::I32_WRAP_I64 => 'i32.wrap_i64',
        self::I32_TRUNC_F32_S => 'i32.trunc_f32_s',
        self::I32_TRUNC_F32_U => 'i32.trunc_f32_u',
        self::I32_TRUNC_F64_S => 'i32.trunc_f64_s',
        self::I32_TRUNC_F64_U => 'i32.trunc_f64_u',
        self::I64_EXTEND_I32_S => 'i64.extend_i32_s',
        self::I64_EXTEND_I32_U => 'i64.extend_i32_u',
        self::I64_TRUNC_F32_S => 'i64.trunc_f32_s',
        self::I64_TRUNC_F32_U => 'i64.trunc_f32_u',
        self::I64_TRUNC_F64_S => 'i64.trunc_f64_s',
        self::I64_TRUNC_F64_U => 'i64.trunc_f64_u',
        self::F32_CONVERT_I32_S => 'f32.convert_i32_s',
        self::F32_CONVERT_I32_U => 'f32.convert_i32_u',
        self::F32_CONVERT_I64_S => 'f32.convert_i64_s',
        self::F32_CONVERT_I64_U => 'f32.convert_i64_u',
        self::F32_DEMOTE_F64 => 'f32.demote_f64',
        self::F64_CONVERT_I32_S => 'f64.convert_i32_s',
        self::F64_CONVERT_I32_U => 'f64.convert_i32_u',
        self::F64_CONVERT_I64_S => 'f64.convert_i64_s',
        self::F64_CONVERT_I64_U => 'f64.convert_i64_u',
        self::F64_PROMOTE_F32 => 'f64.promote_f32',
        self::I32_REINTERPRET_F32 => 'i32.reinterpret_f32',
        self::I64_REINTERPRET_F64 => 'i64.reinterpret_f64',
        self::F32_REINTERPRET_I32 => 'f32.reinterpret_i32',
        self::F64_REINTERPRET_I64 => 'f64.reinterpret_i64',

        self::I32_EXTEND8_S => 'i32.extend8_s',
        self::I32_EXTEND16_S => 'i32.extend16_s',
        self::I64_EXTEND8_S => 'i64.extend8_s',
        self::I64_EXTEND16_S => 'i64.extend16_s',
        self::I64_EXTEND32_S => 'i64.extend32_s',

        self::REF_NULL => 'ref.null',
        self::REF_IS_NULL => 'ref.is_null',
        self::REF_FUNC => 'ref.func',

        self::I32_TRUNC_SAT_F32_S => 'i32.trunc_sat_f32_s',
        self::I32_TRUNC_SAT_F32_U => 'i32.trunc_sat_f32_u',
        self::I32_TRUNC_SAT_F64_S => 'i32.trunc_sat_f64_s',
        self::I32_TRUNC_SAT_F64_U => 'i32.trunc_sat_f64_u',
        self::I64_TRUNC_SAT_F32_S => 'i64.trunc_sat_f32_s',
        self::I64_TRUNC_SAT_F32_U => 'i64.trunc_sat_f32_u',
        self::I64_TRUNC_SAT_F64_S => 'i64.trunc_sat_f64_s',
        self::I64_TRUNC_SAT_F64_U => 'i64.trunc_sat_f64_u',
        self::MEMORY_INIT => 'memory.init',
        self::DATA_DROP => 'data.drop',
        self::MEMORY_COPY => 'memory.copy',
        self::MEMORY_FILL => 'memory.fill',
        self::TABLE_INIT => 'table.init',
        self::ELEM_DROP => 'elem.drop',
        self::TABLE_COPY => 'table.copy',
        self::TABLE_GROW => 'table.grow',
        self::TABLE_SIZE => 'table.size',
        self::TABLE_FILL => 'table.fill',
    ];

    /**
     * @var array<string, int>|null
     */
    private static ?array $opcodesByName = null;

    public static function getMnemonic(int $opcode): string
    {
        return self::$mnemonics[$opcode] ?? sprintf('unknown_0x%02X', $opcode);
    }

    public static function getOpcodeByName(string $name): ?int
    {
        if (self::$opcodesByName === null) {
            self::$opcodesByName = array_flip(self::$mnemonics);
        }
        return self::$opcodesByName[$name] ?? null;
    }

    public static function isControl(int $opcode): bool
    {
        return $opcode >= 0x00 && $opcode <= 0x11;
    }

    public static function isMemoryLoad(int $opcode): bool
    {
        return $opcode >= 0x28 && $opcode <= 0x35;
    }

    public static function isMemoryStore(int $opcode): bool
    {
        return $opcode >= 0x36 && $opcode <= 0x3E;
    }

    public static function isConstant(int $opcode): bool
    {
        return $opcode >= 0x41 && $opcode <= 0x44;
    }

    public static function isBlockTerminator(int $opcode): bool
    {
        return $opcode === self::END || $opcode === self::ELSE;
    }
}
