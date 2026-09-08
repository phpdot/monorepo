<?php

declare(strict_types=1);

namespace PHPdot\WebAuthn\Tests\Support;

use PHPdot\Contracts\Session\SessionInterface;

/**
 * In-memory SessionInterface double for the ceremony store — real behaviour
 * for the keys the store touches, honest no-ops for the flash and CSRF
 * surface nothing under test reads.
 */
final class ArraySession implements SessionInterface
{
    /**
     * @var array<string, mixed>
     */
    public array $data = [];

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
        $this->data['flash.' . $key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->get('flash.' . $key, $default);
    }

    public function hasFlash(string $key): bool
    {
        return $this->has('flash.' . $key);
    }

    public function reflash(): void {}

    public function keep(array $keys): void {}

    public function id(): string
    {
        return 'test-session-id';
    }

    public function regenerate(bool $destroy = false): void {}

    public function invalidate(): void
    {
        $this->data = [];
    }

    public function isStarted(): bool
    {
        return true;
    }

    public function token(): string
    {
        return 'csrf-token';
    }

    public function regenerateToken(): string
    {
        return 'csrf-token';
    }

    public function createdAt(): int
    {
        return 1_000;
    }

    public function lastActivity(): int
    {
        return 1_000;
    }
}
