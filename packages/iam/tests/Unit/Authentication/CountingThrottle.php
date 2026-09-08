<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authentication;

use PHPdot\Iam\Authentication\Contract\LoginThrottleInterface;

/**
 * Recording LoginThrottleInterface double: closeable on demand, remembering
 * every failure and clear it was asked to record.
 */
final class CountingThrottle implements LoginThrottleInterface
{
    public bool $closed = false;

    /**
     * @var list<string>
     */
    public array $failures = [];

    /**
     * @var list<string>
     */
    public array $cleared = [];

    public function allows(string $key): bool
    {
        return !$this->closed;
    }

    public function registerFailure(string $key): void
    {
        $this->failures[] = $key;
    }

    public function clear(string $key): void
    {
        $this->cleared[] = $key;
    }
}
