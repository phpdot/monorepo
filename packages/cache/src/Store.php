<?php

declare(strict_types=1);

/**
 * PSR-16 cache implementation wrapping a DriverInterface backend.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Cache;

use PHPdot\Cache\Exception\InvalidArgumentException;
use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;
use Psr\SimpleCache\CacheInterface;

#[Singleton]
#[Binds(CacheInterface::class)]
#[Binds(StoreInterface::class)]
final class Store implements StoreInterface
{
    /**
     * __construct.
     *
     * @param DriverInterface $driver
     */
    public function __construct(
        private readonly DriverInterface $driver,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);

        $value = $this->driver->get($key);

        return $value !== null ? $value : $default;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->validateKey($key);

        $seconds = $this->normalizeTtl($ttl);

        if ($seconds < 0) {
            $this->driver->delete($key);

            return true;
        }

        return $this->driver->set($key, $value, $seconds);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        $this->validateKey($key);

        return $this->driver->delete($key);
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        return $this->driver->clear();
    }

    /**
     * {@inheritDoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $keyArray = $this->iterableToList($keys);

        foreach ($keyArray as $key) {
            $this->validateKey($key);
        }

        $results = $this->driver->getMultiple($keyArray);
        $output = [];

        foreach ($keyArray as $key) {
            $output[$key] = \array_key_exists($key, $results) ? $results[$key] : $default;
        }

        return $output;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable<mixed> $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl Optional TTL.
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        $valuesArray = $this->iterableToAssocUnsafe($values);

        foreach (array_keys($valuesArray) as $key) {
            $this->validateKey($key);
        }

        $seconds = $this->normalizeTtl($ttl);

        if ($seconds < 0) {
            $this->driver->deleteMultiple(array_keys($valuesArray));

            return false;
        }

        return $this->driver->setMultiple($valuesArray, $seconds);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $keyArray = $this->iterableToList($keys);

        foreach ($keyArray as $key) {
            $this->validateKey($key);
        }

        return $this->driver->deleteMultiple($keyArray);
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        $this->validateKey($key);

        return $this->driver->has($key);
    }

    /**
     * {@inheritDoc}
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $this->validateKey($key);

        $value = $this->driver->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->driver->set($key, $value, $ttl);

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, 0, $callback);
    }

    /**
     * Validate a cache key against PSR-16 rules.
     *
     * @param string $key
     *
     * @throws InvalidArgumentException If the key is empty or contains reserved characters.
     *
     * @return void
     */
    private function validateKey(string $key): void
    {
        if ($key === '') {
            throw new InvalidArgumentException('Cache key must not be empty.');
        }

        if (\preg_match('/[{}()\/\\\\@:]/', $key) === 1) {
            throw new InvalidArgumentException(
                \sprintf('Cache key "%s" contains one or more reserved characters: {}()/\\@:', $key),
            );
        }
    }

    /**
     * Normalize a PSR-16 TTL value to integer seconds.
     *
     * @param \DateInterval|int|null $ttl
     *
     * @return int
     */
    private function normalizeTtl(null|int|\DateInterval $ttl): int
    {
        if ($ttl === null) {
            return 0;
        }

        if ($ttl instanceof \DateInterval) {
            return (new \DateTime())->add($ttl)->getTimestamp() - \time();
        }

        return $ttl;
    }

    /**
     * Convert an iterable of keys to a list of strings.
     *
     * @param iterable<string> $keys
     *
     * @return list<string>
     */
    private function iterableToList(iterable $keys): array
    {
        if (\is_array($keys)) {
            return \array_values($keys);
        }

        return \iterator_to_array($keys, false);
    }

    /**
     * Convert an untyped PSR-16 iterable to an associative array.
     *
     * @param iterable<mixed> $values
     *
     * @return array<string, mixed>
     */
    private function iterableToAssocUnsafe(iterable $values): array
    {
        if (\is_array($values)) {
            $result = [];

            foreach ($values as $key => $value) {
                $result[(string) $key] = $value;
            }

            return $result;
        }

        $result = [];

        foreach ($values as $key => $value) {
            if (\is_string($key) || \is_int($key)) {
                $result[(string) $key] = $value;
            }
        }

        return $result;
    }
}
