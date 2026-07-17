<?php

declare(strict_types=1);

/**
 * Redis cache driver using ext-redis.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Cache\Driver;

use PHPdot\Cache\DriverInterface;
use PHPdot\Cache\Serializer;

final class RedisDriver implements DriverInterface
{
    /**
     * Create the Redis-backed cache driver.
     *
     * @param \Redis $redis Redis connection instance.
     * @param string $prefix Key prefix for namespacing.
     * @param Serializer $serializer Value serializer.
     */
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $prefix = '',
        private readonly Serializer $serializer = new Serializer(),
    ) {}

    /**
     * {@inheritDoc}
     */
    public function get(string $key): mixed
    {
        $result = $this->redis->get($this->prefix . $key);

        if (!\is_string($result)) {
            return null;
        }

        return $this->serializer->unserialize($result);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $data = $this->serializer->serialize($value);
        $prefixed = $this->prefix . $key;

        if ($ttl > 0) {
            return $this->redis->setex($prefixed, $ttl, $data);
        }

        return $this->redis->set($prefixed, $data) !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        return $this->redis->del($this->prefix . $key) > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        if ($this->prefix === '') {
            return $this->redis->flushDB();
        }

        $cursor = null;
        $pattern = $this->prefix . '*';

        do {
            $keys = $this->redis->scan($cursor, $pattern, 1000);

            if ($keys !== false && $keys !== []) {
                $this->redis->del(...$keys);
            }
        } while ($cursor > 0);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        $result = $this->redis->exists($this->prefix . $key);

        return (\is_int($result) ? $result : 0) > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function getMultiple(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $prefixedKeys = \array_map(fn(string $key): string => $this->prefix . $key, $keys);
        $values = $this->redis->mget($prefixedKeys);

        $results = [];

        foreach ($keys as $i => $key) {
            $val = $values[$i] ?? false;

            if (\is_string($val)) {
                $results[$key] = $this->serializer->unserialize($val);
            }
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function setMultiple(array $values, int $ttl = 0): bool
    {
        if ($values === []) {
            return true;
        }

        $this->redis->multi(\Redis::PIPELINE);

        foreach ($values as $key => $value) {
            $data = $this->serializer->serialize($value);
            $prefixed = $this->prefix . $key;

            if ($ttl > 0) {
                $this->redis->setex($prefixed, $ttl, $data);
            } else {
                $this->redis->set($prefixed, $data);
            }
        }

        $this->redis->exec();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        if ($keys === []) {
            return true;
        }

        $prefixedKeys = \array_map(fn(string $key): string => $this->prefix . $key, $keys);

        return $this->redis->del(...$prefixedKeys) > 0;
    }
}
