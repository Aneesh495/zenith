<?php

declare(strict_types=1);

namespace Zenith\Validator;

use Zenith\Ast\BlockType;
use Zenith\Ast\FuncType;
use Zenith\Ast\Module;
use Zenith\Ast\ValueType;

/**
 * Validation context maintaining abstract value stack and control frames.
 *
 * Implements the Wasm 2.0 type validation algorithm with polymorphic stack handling.
 */
final class ValidationContext
{
    /** @var list<ValueType|null> */
    public array $opStack = [];
    /** @var list<ControlFrame> */
    public array $controlStack = [];

    /**
     * @param Module $module Parent module AST
     * @param list<ValueType> $locals Local variable types including parameters
     * @param list<ValueType> $returns Expected function return types
     */
    public function __construct(
        public readonly Module $module,
        public readonly array $locals = [],
        public readonly array $returns = [],
    ) {
    }

    public function pushVal(?ValueType $type): void
    {
        $this->opStack[] = $type;
    }

    public function popVal(?ValueType $expected = null): ?ValueType
    {
        $ctrl = $this->topCtrl();

        if (count($this->opStack) === $ctrl->height) {
            if ($ctrl->unreachable) {
                return $expected;
            }
            throw new ValidationError('Stack underflow in control frame');
        }

        $actual = array_pop($this->opStack);

        if ($actual === null) {
            return $expected;
        }

        if ($expected !== null && $actual !== $expected) {
            throw new ValidationError(sprintf(
                'Type mismatch: expected %s, got %s',
                $expected->name(),
                $actual->name()
            ));
        }

        return $actual;
    }

    public function pushVals(array $types): void
    {
        foreach ($types as $type) {
            $this->pushVal($type);
        }
    }

    public function popVals(array $types): void
    {
        for ($i = count($types) - 1; $i >= 0; $i--) {
            $this->popVal($types[$i]);
        }
    }

    public function pushCtrl(int $opcode, array $startTypes, array $endTypes): void
    {
        $frame = new ControlFrame($opcode, $startTypes, $endTypes, count($this->opStack));
        $this->controlStack[] = $frame;
        $this->pushVals($startTypes);
    }

    public function popCtrl(): ControlFrame
    {
        if (empty($this->controlStack)) {
            throw new ValidationError('Control stack underflow');
        }

        $frame = $this->topCtrl();
        $this->popVals($frame->endTypes);
        array_pop($this->controlStack);

        if (count($this->opStack) !== $frame->height) {
            throw new ValidationError(sprintf(
                'Stack height mismatch at end of block: expected %d, got %d',
                $frame->height,
                count($this->opStack)
            ));
        }

        return $frame;
    }

    public function topCtrl(): ControlFrame
    {
        if (empty($this->controlStack)) {
            throw new ValidationError('Control stack empty');
        }
        return $this->controlStack[count($this->controlStack) - 1];
    }

    public function getLabel(int $depth): ControlFrame
    {
        $idx = count($this->controlStack) - 1 - $depth;
        if ($idx < 0 || $idx >= count($this->controlStack)) {
            throw new ValidationError("Invalid branch label depth: {$depth}");
        }
        return $this->controlStack[$idx];
    }

    public function setUnreachable(): void
    {
        $ctrl = $this->topCtrl();
        $ctrl->unreachable = true;
        // Truncate stack to height of current control frame
        $this->opStack = array_slice($this->opStack, 0, $ctrl->height);
    }

    /**
     * @return array{0: list<ValueType>, 1: list<ValueType>}
     */
    public function expandBlockType(BlockType $type): array
    {
        if ($type->isEmpty) {
            return [[], []];
        }

        if ($type->valType !== null) {
            return [[], [$type->valType]];
        }

        if ($type->typeIndex !== null) {
            $ft = $this->module->types[$type->typeIndex] ?? throw new ValidationError("Missing block type {$type->typeIndex}");
            return [$ft->params, $ft->results];
        }

        return [[], []];
    }

    public function hasControlFrames(): bool
    {
        return !empty($this->controlStack);
    }
}
