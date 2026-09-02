<?php

declare(strict_types=1);

namespace Zenith\Wasi;

/**
 * Execution context and environment configuration for WASI Preview 1.
 */
final class WasiContext
{
    /** @var array<int, FileDescriptor> */
    private array $fds = [];

    /**
     * @param list<string> $args Command line arguments passed to the Wasm program
     * @param array<string, string> $environ Environment variable map
     * @param list<string> $preopens List of preopened directory paths
     * @param bool $captureStdio Whether to redirect stdout/stderr to internal memory buffers
     */
    public function __construct(
        public readonly array $args = [],
        public readonly array $environ = [],
        array $preopens = ['.'],
        bool $captureStdio = false,
    ) {
        if ($captureStdio) {
            $this->fds[0] = FileDescriptor::memory();
            $this->fds[1] = FileDescriptor::memory();
            $this->fds[2] = FileDescriptor::memory();
        } else {
            $this->fds[0] = FileDescriptor::fromStream(defined('STDIN') ? STDIN : fopen('php://stdin', 'r'));
            $this->fds[1] = FileDescriptor::fromStream(defined('STDOUT') ? STDOUT : fopen('php://stdout', 'w'));
            $this->fds[2] = FileDescriptor::fromStream(defined('STDERR') ? STDERR : fopen('php://stderr', 'w'));
        }

        $nextFd = 3;
        foreach ($preopens as $path) {
            $this->fds[$nextFd++] = FileDescriptor::preopenDir($path);
        }
    }

    public function getFd(int $fd): ?FileDescriptor
    {
        return $this->fds[$fd] ?? null;
    }

    public function setFd(int $fd, FileDescriptor $desc): void
    {
        $this->fds[$fd] = $desc;
    }

    public function openFd(FileDescriptor $desc): int
    {
        $fd = 3;
        while (isset($this->fds[$fd])) {
            $fd++;
        }
        $this->fds[$fd] = $desc;
        return $fd;
    }

    public function closeFd(int $fd): bool
    {
        if (!isset($this->fds[$fd])) {
            return false;
        }

        $this->fds[$fd]->close();
        unset($this->fds[$fd]);
        return true;
    }

    public function getStdoutBuffer(): string
    {
        return isset($this->fds[1]) ? $this->fds[1]->getBuffer() : '';
    }

    public function getStderrBuffer(): string
    {
        return isset($this->fds[2]) ? $this->fds[2]->getBuffer() : '';
    }
}
