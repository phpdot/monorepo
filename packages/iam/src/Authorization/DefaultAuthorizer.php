<?php

declare(strict_types=1);

/**
 * has(): possession for the current actor — identity from the scoped
 * IdentityContext, effective permissions from the provider, ONE resolution
 * per request (cache keyed by the actor — a mid-request identity change
 * re-resolves). One uniform code path — membership, nothing else: root is
 * data (the root role holds the full catalog), never a bypass branch.
 * Fail-closed: no identity means every answer is false; a provider failure
 * PROPAGATES, because storage-down must be visible, never disguised as a
 * denial.
 *
 * can(): resolves the named policy through the container (per-coroutine for
 * app classes — the policy injects whatever the rule needs) and returns its
 * decision. Everything about the call is validated loudly: a class that is
 * not a policy, not invokable, or answering non-bool is an
 * InvalidPolicyException, never a quiet deny.
 *
 * Scoped: the caches are request-local by construction.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Scoped;
use PHPdot\Iam\Authorization\Contract\AuthorizerInterface;
use PHPdot\Iam\Authorization\Contract\PermissionProviderInterface;
use PHPdot\Iam\Authorization\Contract\PolicyInterface;
use PHPdot\Iam\Exception\InvalidPolicyException;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;
use PHPdot\Iam\Identities\IdentityContext;
use Psr\Container\ContainerInterface;

#[Scoped]
#[Binds(AuthorizerInterface::class)]
final class DefaultAuthorizer implements AuthorizerInterface
{
    private null|string $cachedFor = null;

    /**
     * @var list<string>|null
     */
    private null|array $permissions = null;

    /**
     * @param IdentityContext $context The per-request actor holder
     * @param PermissionProviderInterface $provider The capability storage seam
     * @param ContainerInterface $container Resolves policy classes per call
     */
    public function __construct(
        private readonly IdentityContext $context,
        private readonly PermissionProviderInterface $provider,
        private readonly ContainerInterface $container,
    ) {}

    /**
     * @inheritDoc
     */
    public function has(string $permission): bool
    {
        return in_array($permission, $this->effectivePermissions(), true);
    }

    /**
     * @inheritDoc
     */
    public function hasAny(array $permissions): bool
    {
        return array_intersect($permissions, $this->effectivePermissions()) !== [];
    }

    /**
     * @inheritDoc
     */
    public function hasAll(array $permissions): bool
    {
        if ($permissions === []) {
            return false;
        }

        return array_diff($permissions, $this->effectivePermissions()) === [];
    }

    /**
     * @inheritDoc
     */
    public function can(string $policy, IdentityInterface $identity, IamResource $resource): bool
    {
        if (!class_exists($policy) || !is_subclass_of($policy, PolicyInterface::class)) {
            throw InvalidPolicyException::notAPolicy($policy);
        }

        $instance = $this->container->get($policy);

        if (!is_callable($instance)) {
            throw InvalidPolicyException::notInvokable($policy);
        }

        $decision = $instance($identity, $resource);

        if (!is_bool($decision)) {
            throw InvalidPolicyException::nonBoolDecision($policy);
        }

        return $decision;
    }

    /**
     * @inheritDoc
     */
    public function identity(): null|IdentityInterface
    {
        return $this->context->current();
    }

    /**
     * Effective permission keys, resolved once per actor per request.
     *
     * @return list<string>
     */
    private function effectivePermissions(): array
    {
        $identity = $this->context->current();

        if ($identity === null) {
            $this->cachedFor = null;
            $this->permissions = [];

            return [];
        }

        $actorKey = $identity->type() . ':' . (string) ($identity->id() ?? '');

        if ($actorKey !== $this->cachedFor) {
            $this->permissions = $this->provider->permissionsFor($identity);
            $this->cachedFor = $actorKey;
        }

        return $this->permissions ?? [];
    }
}
