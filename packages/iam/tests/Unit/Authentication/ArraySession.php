<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authentication;

use PHPdot\Contracts\Session\SessionInterface;

/**
 * In-memory SessionInterface double for testing the session-backed classes.
 * Backs get/set/has/remove/regenerate/invalidate with real behavior and counts
 * regenerate/invalidate calls so session-fixation defenses can be asserted.
 * The flash lifecycle methods (reflash, keep) are no-ops — nothing under test
 * reads them.
 */
final class ArraySession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<string, mixed> */
    private array $flash = [];

    private string $id = 'test-session-id';

    private string $token = 'csrf-token';

    private int $regenerations = 0;

    private null|bool $lastRegenerateDestroyed = null;

    private int $invalidations = 0;

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function all(): array
    {
        return $this->data;
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function flash(string $key, mixed $value): void
    {
        $this->flash[$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->flash) ? $this->flash[$key] : $default;
    }

    public function hasFlash(string $key): bool
    {
        return array_key_exists($key, $this->flash);
    }

    public function reflash(): void {}

    public function keep(array $keys): void {}

    public function id(): string
    {
        return $this->id;
    }

    public function regenerate(bool $destroy = false): void
    {
        $this->lastRegenerateDestroyed = $destroy;
        ++$this->regenerations;
        $this->id = 'regenerated-' . $this->regenerations;
    }

    public function invalidate(): void
    {
        ++$this->invalidations;
        $this->data = [];
        $this->regenerate();
    }

    public function isStarted(): bool
    {
        return true;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function regenerateToken(): string
    {
        return $this->token = 'new-token';
    }

    public function createdAt(): int
    {
        return 1000;
    }

    public function lastActivity(): int
    {
        return 2000;
    }

    public function regenerations(): int
    {
        return $this->regenerations;
    }

    public function lastRegenerateDestroyed(): null|bool
    {
        return $this->lastRegenerateDestroyed;
    }

    public function invalidations(): int
    {
        return $this->invalidations;
    }
}
