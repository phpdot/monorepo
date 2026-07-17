<?php

declare(strict_types=1);

/**
 * In-memory session handler for testing.
 *
 * Data is not persisted between processes or requests.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Session\Handler;

use PHPdot\Container\Attribute\Scoped;
use PHPdot\Contracts\Session\SessionHandlerInterface;

#[Scoped]
final class ArrayHandler implements SessionHandlerInterface
{
    /**
     * @var array<string, array{data: string, expiry: int}>
     */
    private array $storage = [];

    /**
     * {@inheritDoc}
     */
    public function read(string $id): string
    {
        if (!isset($this->storage[$id])) {
            return '';
        }

        $entry = $this->storage[$id];

        if ($entry['expiry'] > 0 && time() >= $entry['expiry']) {
            unset($this->storage[$id]);

            return '';
        }

        return $entry['data'];
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $id, string $data, int $lifetime): void
    {
        $this->storage[$id] = [
            'data' => $data,
            'expiry' => $lifetime > 0 ? time() + $lifetime : 0,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function destroy(string $id): void
    {
        unset($this->storage[$id]);
    }

    /**
     * {@inheritDoc}
     */
    public function exists(string $id): bool
    {
        if (!isset($this->storage[$id])) {
            return false;
        }

        if ($this->storage[$id]['expiry'] > 0 && time() >= $this->storage[$id]['expiry']) {
            unset($this->storage[$id]);

            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function gc(int $lifetime): int
    {
        $count = 0;
        $now = time();

        foreach ($this->storage as $id => $entry) {
            if ($entry['expiry'] > 0 && $entry['expiry'] <= $now) {
                unset($this->storage[$id]);
                $count++;
            }
        }

        return $count;
    }
}
