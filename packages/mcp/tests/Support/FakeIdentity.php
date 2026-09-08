<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Support;

use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

/**
 * The smallest identity the contract allows.
 */
final class FakeIdentity implements IdentityInterface
{
    public function id(): int|string|null
    {
        return 7;
    }

    public function type(): string
    {
        return 'user';
    }
}
