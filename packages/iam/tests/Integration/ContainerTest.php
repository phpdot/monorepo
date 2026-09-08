<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Integration;

use PHPdot\Container\ContainerBuilder;

use function PHPdot\Container\scoped;

use PHPdot\Container\Testing\TestContextProvider;
use PHPdot\Iam\Authorization\Contract\AssignmentRepositoryInterface;
use PHPdot\Iam\Authorization\Contract\AuthorizerInterface;
use PHPdot\Iam\Authorization\Contract\PermissionCatalogInterface;
use PHPdot\Iam\Authorization\Contract\PermissionProviderInterface;
use PHPdot\Iam\Authorization\Contract\PermissionRepositoryInterface;
use PHPdot\Iam\Authorization\Contract\RoleManagerInterface;
use PHPdot\Iam\Authorization\Contract\RoleRepositoryInterface;
use PHPdot\Iam\Authorization\DefaultAuthorizer;
use PHPdot\Iam\Authorization\DefaultRoleManager;
use PHPdot\Iam\Authorization\Discovery\DiscoveredPermission;
use PHPdot\Iam\Authorization\MemoryPermissionCatalog;
use PHPdot\Iam\Authorization\RepositoryPermissionProvider;
use PHPdot\Iam\Authorization\SeedAssignmentRepository;
use PHPdot\Iam\Authorization\SeedPermissionRepository;
use PHPdot\Iam\Authorization\SeedRoleRepository;
use PHPdot\Iam\Identities\IdentityContext;
use PHPdot\Iam\Identities\Types\UserIdentity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Proves the package wires through the real phpdot container: the
 * #[Scoped]/#[Binds] attributes are discovered by a directory scan, the
 * host-implementation seams bind over the seed world, and the scoped manager
 * never captures state across resolutions — the captive-dependency trap the
 * singleton attribute invited.
 */
final class ContainerTest extends TestCase
{
    #[Test]
    public function theAttributeWiringResolvesOverHostSeams(): void
    {
        $requests = new TestContextProvider();
        $container = (new ContainerBuilder())
            ->withContextProvider($requests)
            ->scanAttributesIn(\dirname(__DIR__, 2) . '/src')
            ->addDefinitions([
                AuthorizerInterface::class => scoped(
                    static fn(ContainerInterface $c): DefaultAuthorizer => $c->get(DefaultAuthorizer::class),
                ),
                RoleManagerInterface::class => scoped(
                    static fn(ContainerInterface $c): DefaultRoleManager => $c->get(DefaultRoleManager::class),
                ),
                PermissionCatalogInterface::class => scoped(static fn(): MemoryPermissionCatalog => new MemoryPermissionCatalog([
                    new DiscoveredPermission('hr.employee.edit', 'Edit employees', '', false, 'Fixture::class'),
                ])),
                RoleRepositoryInterface::class => scoped(
                    static fn(ContainerInterface $c): SeedRoleRepository => new SeedRoleRepository(
                        $c->get(PermissionCatalogInterface::class),
                        [],
                        ['guest' => []],
                    ),
                ),
                AssignmentRepositoryInterface::class => scoped(
                    static fn(ContainerInterface $c): SeedAssignmentRepository => new SeedAssignmentRepository(
                        $c->get(RoleRepositoryInterface::class),
                        ['user:42' => ['root']],
                    ),
                ),
                PermissionRepositoryInterface::class => scoped(
                    static fn(ContainerInterface $c): SeedPermissionRepository => new SeedPermissionRepository(
                        $c->get(PermissionCatalogInterface::class),
                    ),
                ),
                PermissionProviderInterface::class => scoped(
                    static fn(ContainerInterface $c): RepositoryPermissionProvider => new RepositoryPermissionProvider(
                        $c->get(AssignmentRepositoryInterface::class),
                        $c->get(RoleRepositoryInterface::class),
                    ),
                ),
            ])
            ->build();

        $authorizer = $container->get(AuthorizerInterface::class);

        self::assertInstanceOf(DefaultAuthorizer::class, $authorizer);

        $context = $container->get(IdentityContext::class);
        $context->setCurrent(new UserIdentity(42));

        self::assertTrue($authorizer->has('hr.employee.edit'), 'root is data: the assignment resolves to the full catalog');

        $first = $container->get(RoleManagerInterface::class);
        self::assertInstanceOf(DefaultRoleManager::class, $first);
        self::assertSame($first, $container->get(RoleManagerInterface::class), 'within one request the scoped manager is stable');

        $requests->newContext('next-request');

        self::assertNotSame(
            $first,
            $container->get(RoleManagerInterface::class),
            'the manager is scoped per request, never captured for the worker\'s life',
        );
    }
}
