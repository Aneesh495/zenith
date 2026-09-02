<?php

declare(strict_types=1);

namespace Zenith\Wasi;

/**
 * Representation of an open file descriptor in the WASI subsystem.
 */
final class FileDescriptor
{
    /**
     * @param resource|null $stream Underlying PHP stream resource
     * @param string $buffer In-memory buffer if stream is null
     * @param int $cursor Current seek position for in-memory buffer
     * @param bool $isPreopen Whether this descriptor is a preopened directory
     * @param string|null $preopenPath Path if preopened directory
     */
    public function __construct(
        private mixed $stream = null,
        private string $buffer = '',
        private int $cursor = 0,
        public readonly bool $isPreopen = false,
        public readonly ?string $preopenPath = null,
    ) {
    }

    public static function fromStream(mixed $stream): self
    {
        return new self($stream);
    }

    public static function memory(string $initial = ''): self
    {
        return new self(null, $initial, 0);
    }

    public static function preopenDir(string $path): self
    {
        return new self(null, '', 0, true, $path);
    }

    public function read(int $length): string
    {
        if ($this->stream !== null) {
            $data = fread($this->stream, $length);
            return $data !== false ? $data : '';
        }

        $remaining = max(0, strlen($this->buffer) - $this->cursor);
        $toRead = min($length, $remaining);
        $chunk = substr($this->buffer, $this->cursor, $toRead);
        $this->cursor += $toRead;
        return $chunk;
    }

    public function write(string $data): int
    {
        if ($this->stream !== null) {
            $res = fwrite($this->stream, $data);
            return $res !== false ? $res : 0;
        }

        $len = strlen($data);
        $bufLen = strlen($this->buffer);

        if ($this->cursor >= $bufLen) {
            $this->buffer .= $data;
        } else {
            $this->buffer = substr($this->buffer, 0, $this->cursor) . $data . substr($this->buffer, $this->cursor + $len);
        }

        $this->cursor += $len;
        return $len;
    }

    public function seek(int $offset, int $whence): int
    {
        if ($this->stream !== null) {
            fseek($this->stream, $offset, $whence);
            return ftell($this->stream);
        }

        $bufLen = strlen($this->buffer);
        $newPos = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->cursor + $offset,
            SEEK_END => $bufLen + $offset,
            default => -1,
        };

        if ($newPos < 0) {
            return -1;
        }

        $this->cursor = $newPos;
        return $this->cursor;
    }

    public function tell(): int
    {
        if ($this->stream !== null) {
            return ftell($this->stream);
        }
        return $this->cursor;
    }

    public function close(): void
    {
        if ($this->stream !== null && is_resource($this->stream)) {
            fclose($this->stream);
            $this->stream = null;
        }
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }
}
