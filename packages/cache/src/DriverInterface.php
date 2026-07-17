<?php

declare(strict_types=1);

/**
 * Raw backend contract for cache drivers.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Cache;

interface DriverInterface
{
    /**
     * Retrieve a value by key.
     *
     * @param string $key
     *
     * @return mixed The cached value or null if not found.
     */
    public function get(string $key): mixed;

    /**
     * Store a value by key with optional TTL in seconds.
     *
     * @param int $ttl Time-to-live in seconds (0 = no expiry).
     * @param mixed $value
     * @param string $key
     *
     * @return bool
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool;

    /**
     * Remove a value by key.
     *
     * @param string $key
     *
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * Wipe all cached values.
     *
     * @return bool
     */
    public function clear(): bool;

    /**
     * Check whether a key exists and is not expired.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Retrieve multiple values by their keys.
     *
     * @param list<string> $keys
     *
     * @return array<string, mixed> Key => value (missing keys not included).
     */
    public function getMultiple(array $keys): array;

    /**
     * Store multiple key => value pairs with optional TTL.
     *
     * @param array<string, mixed> $values Key => value.
     * @param int $ttl Time-to-live in seconds (0 = no expiry).
     *
     * @return bool
     */
    public function setMultiple(array $values, int $ttl = 0): bool;

    /**
     * Remove multiple values by their keys.
     *
     * @param list<string> $keys
     *
     * @return bool
     */
    public function deleteMultiple(array $keys): bool;
}
