<?php

declare(strict_types=1);

/**
 * APCu cache driver using ext-apcu.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Cache\Driver;

use PHPdot\Cache\DriverInterface;

final class ApcuDriver implements DriverInterface
{
    /**
     * Create the APCu-backed cache driver.
     *
     * @param string $prefix Key prefix for namespacing.
     */
    public function __construct(
        private readonly string $prefix = '',
    ) {}

    /**
     * {@inheritDoc}
     */
    public function get(string $key): mixed
    {
        $success = false;
        $value = \apcu_fetch($this->prefix . $key, $success);

        return $success ? $value : null;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return \apcu_store($this->prefix . $key, $value, $ttl);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        return \apcu_delete($this->prefix . $key) !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        return \apcu_clear_cache();
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return \apcu_exists($this->prefix . $key) !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function getMultiple(array $keys): array
    {
        $results = [];

        foreach ($keys as $key) {
            $value = $this->get($key);

            if ($value !== null) {
                $results[$key] = $value;
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
