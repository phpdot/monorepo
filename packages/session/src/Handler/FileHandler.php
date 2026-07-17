<?php

declare(strict_types=1);

/**
 * File-based session handler with advisory locking.
 *
 * Uses shared locks for reads and exclusive locks for writes
 * to prevent data corruption under concurrent access.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Session\Handler;

use PHPdot\Contracts\Session\SessionHandlerInterface;
use PHPdot\Session\Exception\SessionReadException;
use PHPdot\Session\Exception\SessionWriteException;

final class FileHandler implements SessionHandlerInterface
{
    /**
     * A filesystem session handler that stores each session in a file.
     *
     * @param string $directory
     */
    public function __construct(
        private readonly string $directory,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function read(string $id): string
    {
        $path = $this->path($id);

        if (!is_file($path)) {
            return '';
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new SessionReadException(
                \sprintf('Unable to open session file: %s', $path),
                $id,
            );
        }

        try {
            flock($handle, LOCK_SH);
            $contents = stream_get_contents($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if ($contents === false) {
            return '';
        }

        return $contents;
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $id, string $data, int $lifetime): void
    {
        $this->ensureDirectory();

        $path = $this->path($id);
        $handle = @fopen($path, 'cb');

        if ($handle === false) {
            throw new SessionWriteException(
                \sprintf('Unable to open session file for writing: %s', $path),
                $id,
            );
        }

        try {
            flock($handle, LOCK_EX);
            ftruncate($handle, 0);
            fwrite($handle, $data);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function destroy(string $id): void
    {
        $path = $this->path($id);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function exists(string $id): bool
    {
        return is_file($this->path($id));
    }

    /**
     * {@inheritDoc}
     */
    public function gc(int $lifetime): int
    {
        if ($lifetime <= 0) {
            return 0;
        }

        if (!is_dir($this->directory)) {
            return 0;
        }

        $count = 0;
        $cutoff = time() - $lifetime;
        $items = @scandir($this->directory);

        if ($items === false) {
            return 0;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $this->directory . '/' . $item;

            if (!is_file($path)) {
                continue;
            }

            $mtime = filemtime($path);

            if ($mtime !== false && $mtime < $cutoff) {
                @unlink($path);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Build the file path for a session ID.
     *
     * @param string $id
     *
     * @return string
     */
    private function path(string $id): string
    {
        return $this->directory . '/sess_' . $id;
    }

    /**
     * Ensure the storage directory exists.
     *
     * @return void
     */
    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!@mkdir($this->directory, 0777, true) && !is_dir($this->directory)) {
            throw new SessionWriteException(
                \sprintf('Unable to create session directory: %s', $this->directory),
                '',
            );
        }
    }
}
