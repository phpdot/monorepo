<?php

declare(strict_types=1);

/**
 * Session contract — request-bound read/write API for session state.
 *
 * Provides typed access to session data, flash messages, the CSRF token,
 * and lifecycle operations (regenerate, invalidate). Implementations are
 * mutable and bound to a single request or coroutine for the duration of
 * their lifetime; consumers must not share an instance across requests.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Session;

interface SessionInterface
{
    /**
     * Get a value from the session.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a value in the session.
     *
     * @param mixed $value
     * @param string $key
     *
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Check if a key exists in the session.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Remove a key from the session.
     *
     * @param string $key
     *
     * @return void
     */
    public function remove(string $key): void;

    /**
     * Get all session data (excluding internal metadata).
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Clear all session data.
     *
     * @return void
     */
    public function clear(): void;

    /**
     * Flash a key-value pair for the next request only.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function flash(string $key, mixed $value): void;

    /**
     * Get a flashed value.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getFlash(string $key, mixed $default = null): mixed;

    /**
     * Check if a flash key exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasFlash(string $key): bool;

    /**
     * Keep all flash data for one more request.
     *
     * @return void
     */
    public function reflash(): void;

    /**
     * Keep specific flash keys for one more request.
     *
     * @param list<string> $keys
     *
     * @return void
     */
    public function keep(array $keys): void;

    /**
     * Get the session ID as a string.
     *
     * @return string
     */
    public function id(): string;

    /**
     * Regenerate the session ID. Optionally destroy the old session.
     *
     * @param bool $destroy
     *
     * @return void
     */
    public function regenerate(bool $destroy = false): void;

    /**
     * Destroy the session: clear all data and regenerate the ID.
     *
     * @return void
     */
    public function invalidate(): void;

    /**
     * Whether the session has been started (data loaded).
     *
     * @return bool
     */
    public function isStarted(): bool;

    /**
     * Get the CSRF token, generating one if it does not exist.
     *
     * @return string
     */
    public function token(): string;

    /**
     * Generate a new CSRF token, replacing the existing one.
     *
     * @return string
     */
    public function regenerateToken(): string;

    /**
     * Unix timestamp when the session was first created.
     *
     * @return int
     */
    public function createdAt(): int;

    /**
     * Unix timestamp of the last activity on this session.
     *
     * @return int
     */
    public function lastActivity(): int;
}
