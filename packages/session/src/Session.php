<?php

declare(strict_types=1);

/**
 * Mutable session instance, one per request.
 *
 * Created by SessionManager, attached to the request by SessionMiddleware,
 * and saved back to the handler at the end of the request lifecycle.
 *
 * User data lives in $data; framework-managed metadata (flash bookkeeping,
 * CSRF token, timestamps) lives in dedicated properties so user keys can
 * never collide with internals.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Session;

use PHPdot\Contracts\Session\SessionInterface;

final class Session implements SessionInterface
{
    private bool $started = false;

    private bool $destroyed = false;

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @var list<string>
     */
    private array $flashNew = [];

    /**
     * @var list<string>
     */
    private array $flashOld = [];

    private ?string $token = null;

    /**
     * Hold one session: its id and creation/last-activity timestamps.
     *
     * @param SessionId $id
     * @param int $createdAt
     * @param int $lastActivity
     */
    public function __construct(
        private SessionId $id,
        private readonly int $createdAt,
        private int $lastActivity,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }

    /**
     * {@inheritDoc}
     */
    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): void
    {
        $this->data = [];
    }

    /**
     * {@inheritDoc}
     */
    public function flash(string $key, mixed $value): void
    {
        $this->set($key, $value);

        $this->flashNew = array_values(array_unique([...$this->flashNew, $key]));
        $this->flashOld = array_values(array_diff($this->flashOld, [$key]));
    }

    /**
     * {@inheritDoc}
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    /**
     * {@inheritDoc}
     */
    public function hasFlash(string $key): bool
    {
        return (\in_array($key, $this->flashNew, true) || \in_array($key, $this->flashOld, true))
            && $this->has($key);
    }

    /**
     * {@inheritDoc}
     */
    public function reflash(): void
    {
        $this->flashNew = array_values(array_unique(array_merge($this->flashNew, $this->flashOld)));
        $this->flashOld = [];
    }

    /**
     * {@inheritDoc}
     */
    public function keep(array $keys): void
    {
        $this->flashNew = array_values(array_unique(array_merge(
            $this->flashNew,
            array_values(array_intersect($this->flashOld, $keys)),
        )));
        $this->flashOld = array_values(array_diff($this->flashOld, $keys));
    }

    /**
     * {@inheritDoc}
     */
    public function id(): string
    {
        return $this->id->toString();
    }

    /**
     * {@inheritDoc}
     */
    public function regenerate(bool $destroy = false): void
    {
        if ($destroy) {
            $this->destroyed = true;
        }

        $this->id = SessionId::generate();
    }

    /**
     * {@inheritDoc}
     */
    public function invalidate(): void
    {
        $this->destroyed = true;
        $this->data = [];
        $this->flashNew = [];
        $this->flashOld = [];
        $this->token = null;
        $this->id = SessionId::generate();
    }

    /**
     * {@inheritDoc}
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * {@inheritDoc}
     */
    public function token(): string
    {
        if ($this->token === null || $this->token === '') {
            return $this->regenerateToken();
        }

        return $this->token;
    }

    /**
     * {@inheritDoc}
     */
    public function regenerateToken(): string
    {
        $this->token = bin2hex(random_bytes(32));

        return $this->token;
    }

    /**
     * {@inheritDoc}
     */
    public function createdAt(): int
    {
        return $this->createdAt;
    }

    /**
     * {@inheritDoc}
     */
    public function lastActivity(): int
    {
        return $this->lastActivity;
    }

    /**
     * Whether the old session should be destroyed (regenerate or invalidate was called).
     *
     * @return bool
     */
    public function isDestroyed(): bool
    {
        return $this->destroyed;
    }

    /**
     * Get the SessionId value object.
     *
     * @return SessionId
     */
    public function getSessionId(): SessionId
    {
        return $this->id;
    }

    /**
     * Load deserialized payload into the session and rotate flash.
     *
     * The payload shape mirrors what toArray() produces; keys are optional
     * so an empty payload yields a fresh, started session.
     *
     * @param array<string, mixed> $payload
     *
     * @return void
     */
    public function load(array $payload): void
    {
        $data = $payload['data'] ?? [];
        if (\is_array($data)) {
            /**
             * @var array<string, mixed> $data
             */
            $this->data = $data;
        } else {
            $this->data = [];
        }

        $this->flashOld = $this->extractStringList($payload['flash_new'] ?? []);
        $this->flashNew = [];

        $token = $payload['token'] ?? null;
        $this->token = \is_string($token) ? $token : null;

        $this->started = true;
    }

    /**
     * Get the full session payload for serialization.
     *
     * @return array{
     *     data: array<string, mixed>,
     *     flash_new: list<string>,
     *     flash_old: list<string>,
     *     token: ?string,
     *     created_at: int,
     *     last_activity: int,
     * }
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'flash_new' => $this->flashNew,
            'flash_old' => $this->flashOld,
            'token' => $this->token,
            'created_at' => $this->createdAt,
            'last_activity' => $this->lastActivity,
        ];
    }

    /**
     * Remove old flash keys before saving.
     *
     * @return void
     */
    public function ageFlash(): void
    {
        foreach ($this->flashOld as $key) {
            $this->remove($key);
        }

        $this->flashOld = [];
    }

    /**
     * Update the last activity timestamp.
     *
     * @param int $timestamp
     *
     * @return void
     */
    public function setLastActivity(int $timestamp): void
    {
        $this->lastActivity = $timestamp;
    }

    /**
     * Coerce arbitrary value into a list of strings, dropping non-string entries.
     *
     * @param mixed $value
     *
     * @return list<string>
     */
    private function extractStringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (\is_string($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }
}
