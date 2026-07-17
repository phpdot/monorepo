<?php

declare(strict_types=1);

/**
 * Session handler contract — persistence layer for serialized session data.
 *
 * Implementations store, retrieve, expire, and garbage-collect opaque
 * serialized payloads keyed by session ID. Serialization is performed
 * upstream via SerializerInterface; handlers must treat payloads as
 * opaque and must not interpret their contents.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Session;

interface SessionHandlerInterface
{
    /**
     * Read serialized session data by ID.
     *
     * @param string $id
     *
     * @return string Serialized data, or empty string if not found.
     */
    public function read(string $id): string;

    /**
     * Write serialized session data.
     *
     * @param string $id Session ID.
     * @param string $data Serialized session data.
     * @param int $lifetime Lifetime in seconds. 0 means no expiration.
     *
     * @return void
     */
    public function write(string $id, string $data, int $lifetime): void;

    /**
     * Destroy a session by ID.
     *
     * @param string $id
     *
     * @return void
     */
    public function destroy(string $id): void;

    /**
     * Check whether a session exists.
     *
     * @param string $id
     *
     * @return bool
     */
    public function exists(string $id): bool;

    /**
     * Garbage-collect expired sessions.
     *
     * @param int $lifetime Maximum session lifetime in seconds.
     *
     * @return int Number of sessions removed.
     */
    public function gc(int $lifetime): int;
}
