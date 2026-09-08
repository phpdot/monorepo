<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authentication;

use PHPdot\Iam\Authentication\Contract\PendingAuthStoreInterface;
use PHPdot\Iam\Authentication\PendingAuth;

/**
 * In-memory PendingAuthStoreInterface for tests: holds the half-finished authentication
 * in a property, mirroring what the session-backed store persists across
 * requests — without needing a session.
 */
final class MemoryPendingAuthStore implements PendingAuthStoreInterface
{
    private null|PendingAuth $pending = null;

    public function put(PendingAuth $pending): void
    {
        $this->pending = $pending;
    }

    public function get(): null|PendingAuth
    {
        return $this->pending;
    }

    public function clear(): void
    {
        $this->pending = null;
    }
}
