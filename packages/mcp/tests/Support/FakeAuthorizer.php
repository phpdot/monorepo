<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Support;

use PHPdot\Iam\Authorization\Contract\AuthorizerInterface;
use PHPdot\Iam\Authorization\IamResource;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

/**
 * An Authorizer over a fixed permission set, with or without an identity —
 * enough to prove the bridge delegates and refuses anonymous callers.
 */
final class FakeAuthorizer implements AuthorizerInterface
{
    /**
     * @param array<string> $permissions What this authorizer grants
     * @param bool $identified Whether an identity sits behind it
     */
    public function __construct(
        private readonly array $permissions,
        private readonly bool $identified,
    ) {}

    public function has(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function hasAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->has($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAll(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->has($permission)) {
                return false;
            }
        }

        return true;
    }

    public function can(string $policy, IdentityInterface $identity, IamResource $resource): bool
    {
        return false;
    }

    public function identity(): IdentityInterface|null
    {
        return $this->identified ? new FakeIdentity() : null;
    }
}
