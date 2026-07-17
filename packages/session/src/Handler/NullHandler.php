<?php

declare(strict_types=1);

/**
 * No-op session handler for stateless APIs or testing.
 *
 * All reads return empty, all writes are discarded.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Session\Handler;

use PHPdot\Container\Attribute\Singleton;
use PHPdot\Contracts\Session\SessionHandlerInterface;

#[Singleton]
final class NullHandler implements SessionHandlerInterface
{
    /**
     * {@inheritDoc}
     */
    public function read(string $id): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $id, string $data, int $lifetime): void {}

    /**
     * {@inheritDoc}
     */
    public function destroy(string $id): void {}

    /**
     * {@inheritDoc}
     */
    public function exists(string $id): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function gc(int $lifetime): int
    {
        return 0;
    }
}
