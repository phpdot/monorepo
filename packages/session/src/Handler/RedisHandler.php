<?php

declare(strict_types=1);

/**
 * Redis session handler using ext-redis.
 *
 * Relies on Redis TTL for automatic expiration — gc() is a no-op.
 * Designed to receive a scoped \Redis instance via DI (pool-managed).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Session\Handler;

use PHPdot\Container\Attribute\Scoped;
use PHPdot\Contracts\Session\SessionHandlerInterface;
use PHPdot\Session\Exception\SessionReadException;
use PHPdot\Session\Exception\SessionWriteException;

#[Scoped]
final class RedisHandler implements SessionHandlerInterface
{
    /**
     * A Redis-backed session handler using ext-redis; Redis TTL handles expiry.
     *
     * @param \Redis $redis Redis connection instance.
     * @param string $prefix Key prefix for namespacing.
     */
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $prefix = 'session:',
    ) {}

    /**
     * {@inheritDoc}
     */
    public function read(string $id): string
    {
        try {
            $result = $this->redis->get($this->prefix . $id);

            if (!\is_string($result)) {
                return '';
            }

            return $result;
        } catch (\RedisException $e) {
            throw new SessionReadException(
                'Failed to read session from Redis: ' . $e->getMessage(),
                $id,
                previous: $e,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $id, string $data, int $lifetime): void
    {
        try {
            $key = $this->prefix . $id;

            if ($lifetime > 0) {
                $this->redis->setex($key, $lifetime, $data);
            } else {
                $this->redis->set($key, $data);
            }
        } catch (\RedisException $e) {
            throw new SessionWriteException(
                'Failed to write session to Redis: ' . $e->getMessage(),
                $id,
                previous: $e,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function destroy(string $id): void
    {
        try {
            $this->redis->del($this->prefix . $id);
        } catch (\RedisException $e) {
            throw new SessionWriteException(
                'Failed to destroy session in Redis: ' . $e->getMessage(),
                $id,
                previous: $e,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function exists(string $id): bool
    {
        try {
            $result = $this->redis->exists($this->prefix . $id);

            return (\is_int($result) ? $result : 0) > 0;
        } catch (\RedisException $e) {
            throw new SessionReadException(
                'Failed to check session existence in Redis: ' . $e->getMessage(),
                $id,
                previous: $e,
            );
        }
    }

    /**
     * Redis TTL handles expiration automatically.
     *
     * {@inheritDoc}
     */
    public function gc(int $lifetime): int
    {
        return 0;
    }
}
