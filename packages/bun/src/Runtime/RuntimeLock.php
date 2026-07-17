<?php

declare(strict_types=1);

/**
 * Cross-process guard around the first-use binary download.
 *
 * Two processes (parallel CI jobs, or two concurrent commands) may race to download the binary.
 * The work callback runs while an exclusive flock is held and is expected to re-check whether the
 * binary already exists before downloading.
 *
 * Note: flock is NOT transformed by Swoole's coroutine runtime hooks, so under Swoole acquiring
 * the lock blocks the coroutine. The lock window is only the rare first-use download, so in
 * practice this is a brief, infrequent wait.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Runtime;

use PHPdot\Bun\Exception\BinaryDownloadException;
use PHPdot\Container\Attribute\Singleton;

#[Singleton]
final class RuntimeLock
{
    /**
     * Run $work while holding an exclusive lock on $lockFile.
     *
     * @template T
     *
     * @param callable():T $work
     * @param string $lockFile
     *
     * @throws BinaryDownloadException when the lock directory or file cannot be created/locked
     *
     * @return T
     */
    public function withLock(string $lockFile, callable $work): mixed
    {
        $dir = dirname($lockFile);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new BinaryDownloadException(sprintf('Unable to create runtime directory: %s', $dir));
        }

        $handle = fopen($lockFile, 'c');
        if ($handle === false) {
            throw new BinaryDownloadException(sprintf('Unable to open lock file: %s', $lockFile));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new BinaryDownloadException(sprintf('Unable to acquire lock: %s', $lockFile));
            }

            try {
                return $work();
            } finally {
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }
    }
}
