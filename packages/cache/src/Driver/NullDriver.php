<?php

declare(strict_types=1);

/**
 * No-op cache driver. All reads miss, all writes succeed.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Cache\Driver;

use PHPdot\Cache\DriverInterface;

final class NullDriver implements DriverInterface
{
    /**
     * {@inheritDoc}
     */
    public function get(string $key): mixed
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getMultiple(array $keys): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function setMultiple(array $values, int $ttl = 0): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        return true;
    }
}
