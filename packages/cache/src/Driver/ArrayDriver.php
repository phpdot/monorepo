<?php

declare(strict_types=1);

/**
 * In-memory cache driver with TTL tracking and optional LRU eviction.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Cache\Driver;

use PHPdot\Cache\DriverInterface;
use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;

#[Singleton]
#[Binds(DriverInterface::class)]
final class ArrayDriver implements DriverInterface
{
    /**
     * @var array<string, array{value: mixed, expiry: int}>
     */
    private array $storage = [];

    /**
     * Create the in-memory array cache driver.
     *
     * @param int $maxItems Maximum number of items (0 = unlimited).
     */
    public function __construct(
        private readonly int $maxItems = 0,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function get(string $key): mixed
    {
        if (!isset($this->storage[$key])) {
            return null;
        }

        $entry = $this->storage[$key];

        if ($entry['expiry'] > 0 && \time() >= $entry['expiry']) {
            unset($this->storage[$key]);

            return null;
        }

        if ($this->maxItems > 0) {
            unset($this->storage[$key]);
            $this->storage[$key] = $entry;
        }

        return $entry['value'];
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        if ($this->maxItems > 0 && !isset($this->storage[$key]) && \count($this->storage) >= $this->maxItems) {
            unset($this->storage[\array_key_first($this->storage)]);
        }

        $this->storage[$key] = [
            'value' => $value,
            'expiry' => $ttl > 0 ? \time() + $ttl : 0,
        ];

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        unset($this->storage[$key]);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        $this->storage = [];

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        if (!isset($this->storage[$key])) {
            return false;
        }

        $entry = $this->storage[$key];

        if ($entry['expiry'] > 0 && \time() >= $entry['expiry']) {
            unset($this->storage[$key]);

            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getMultiple(array $keys): array
    {
        $results = [];

        foreach ($keys as $key) {
            if ($this->has($key)) {
                $results[$key] = $this->get($key);
            }
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function setMultiple(array $values, int $ttl = 0): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }
}
