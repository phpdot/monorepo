<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authorization;

use PHPdot\Iam\Authorization\Contract\PermissionProviderInterface;
use PHPdot\Iam\Authorization\Contract\PolicyInterface;
use PHPdot\Iam\Authorization\DefaultAuthorizer;
use PHPdot\Iam\Authorization\IamResource;
use PHPdot\Iam\Authorization\MemoryPermissionCatalog;
use PHPdot\Iam\Authorization\RepositoryPermissionProvider;
use PHPdot\Iam\Authorization\Role;
use PHPdot\Iam\Authorization\SeedAssignmentRepository;
use PHPdot\Iam\Authorization\SeedRoleRepository;
use PHPdot\Iam\Exception\InvalidPolicyException;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;
use PHPdot\Iam\Identities\IdentityContext;
use PHPdot\Iam\Identities\Types\GuestIdentity;
use PHPdot\Iam\Identities\Types\UserIdentity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use stdClass;

/**
 * The two verbs. has(): fail-closed on empty context, membership with one
 * uniform path (no root branch to test — root is data), ONE provider
 * resolution per actor per request (re-resolving only when the actor
 * changes), and provider failures propagating instead of masquerading as
 * denials. can(): the named policy decides for an explicit identity and
 * typed resource; anything that is not a well-formed policy throws loudly.
 */
final class DefaultAuthorizerTest extends TestCase
{
    #[Test]
    public function noIdentityMeansEveryAnswerIsFalse(): void
    {
        $authorizer = $this->authorizer(new IdentityContext());

        self::assertFalse($authorizer->has('hr.employee.edit'));
        self::assertFalse($authorizer->hasAny(['hr.employee.edit', 'site.pages.view']));
        self::assertFalse($authorizer->hasAny([]));
        self::assertFalse($authorizer->hasAll(['hr.employee.edit']));
        self::assertFalse($authorizer->hasAll([]));
        self::assertNull($authorizer->identity());
    }

    #[Test]
    public function membershipAnswersThePossessionQuestions(): void
    {
        $context = new IdentityContext();
        $context->setCurrent(new UserIdentity(42));

        $authorizer = $this->authorizer($context);

        self::assertTrue($authorizer->has('hr.employee.edit'));
        self::assertFalse($authorizer->has('hr.payroll.view'));
        self::assertTrue($authorizer->hasAny(['hr.payroll.view', 'hr.employee.edit']));
        self::assertFalse($authorizer->hasAll(['hr.employee.edit', 'hr.payroll.view']));
        self::assertTrue($authorizer->hasAll(['hr.employee.edit', 'hr.employee.view']));
        self::assertFalse($authorizer->hasAll([]));
    }

    #[Test]
    public function guestsHoldTheGuestRolesGrants(): void
    {
        $context = new IdentityContext();
        $context->setCurrent(new GuestIdentity());

        $authorizer = $this->authorizer($context);

        self::assertTrue($authorizer->has('site.pages.view'));
        self::assertFalse($authorizer->has('hr.employee.edit'));
    }

    #[Test]
    public function theProviderIsConsultedOncePerActor(): void
    {
        $counting = new class implements PermissionProviderInterface {
            public int $calls = 0;

            public function permissionsFor(IdentityInterface $identity): array
            {
                $this->calls++;

                return ['hr.employee.edit'];
            }

            public function rolesFor(IdentityInterface $identity): array
            {
                return ['hr-editor'];
            }
        };

        $context = new IdentityContext();
        $context->setCurrent(new UserIdentity(42));

        $authorizer = new DefaultAuthorizer($context, $counting, $this->container());

        $authorizer->has('hr.employee.edit');
        $authorizer->hasAny(['a.b.c']);
        $authorizer->hasAll(['hr.employee.edit']);

        self::assertSame(1, $counting->calls, 'three questions, one resolution');

        $context->setCurrent(new UserIdentity(7));
        $authorizer->has('hr.employee.edit');

        self::assertSame(2, $counting->calls, 'a changed actor re-resolves');
    }

    #[Test]
    public function aProviderFailurePropagates(): void
    {
        $failing = new class implements PermissionProviderInterface {
            public function permissionsFor(IdentityInterface $identity): array
            {
                throw new RuntimeException('storage down');
            }

            public function rolesFor(IdentityInterface $identity): array
            {
                return [];
            }
        };

        $context = new IdentityContext();
        $context->setCurrent(new UserIdentity(42));

        $authorizer = new DefaultAuthorizer($context, $failing, $this->container());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('storage down');

        $authorizer->has('hr.employee.edit');
    }

    #[Test]
    public function theNamedPolicyDecides(): void
    {
        $authorizer = $this->authorizer(new IdentityContext());
        $document = new FixtureDocument(ownerId: 42);

        self::assertTrue($authorizer->can(FixtureOwnerPolicy::class, new UserIdentity(42), $document));
        self::assertFalse($authorizer->can(FixtureOwnerPolicy::class, new UserIdentity(7), $document));
    }

    #[Test]
    public function canJudgesAnyIdentityNotJustTheCurrentActor(): void
    {
        $context = new IdentityContext();
        $context->setCurrent(new UserIdentity(7));

        $authorizer = $this->authorizer($context);

        self::assertTrue(
            $authorizer->can(FixtureOwnerPolicy::class, new UserIdentity(42), new FixtureDocument(ownerId: 42)),
            'the identity parameter decides, not the ambient actor',
        );
    }

    #[Test]
    public function aClassThatIsNotAPolicyIsRefused(): void
    {
        $authorizer = $this->authorizer(new IdentityContext());

        $this->expectException(InvalidPolicyException::class);
        $this->expectExceptionMessage('is not a policy');

        $authorizer->can(stdClass::class, new UserIdentity(42), new FixtureDocument(ownerId: 42));
    }

    #[Test]
    public function anUnknownClassIsRefused(): void
    {
        $authorizer = $this->authorizer(new IdentityContext());

        $this->expectException(InvalidPolicyException::class);

        $authorizer->can('Nope\\Missing\\Policy', new UserIdentity(42), new FixtureDocument(ownerId: 42));
    }

    /**
     * An authorizer over the seeded world and a construct-on-demand container.
     *
     * @param IdentityContext $context The actor holder
     *
     * @return DefaultAuthorizer
     */
    private function authorizer(IdentityContext $context): DefaultAuthorizer
    {
        return new DefaultAuthorizer($context, $this->provider(), $this->container());
    }

    /**
     * A minimal PSR container that constructs dependency-free classes.
     *
     * @return ContainerInterface
     */
    private function container(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                /** @var class-string $id */
                return new $id();
            }

            public function has(string $id): bool
            {
                return class_exists($id);
            }
        };
    }

    /**
     * A repository-backed provider with a user, a guest grant, and two roles.
     *
     * @return RepositoryPermissionProvider
     */
    private function provider(): RepositoryPermissionProvider
    {
        $roles = new SeedRoleRepository(
            new MemoryPermissionCatalog([]),
            roles: [new Role(0, 'hr-editor', 'HR editor'), new Role(0, 'hr-admin', 'HR admin')],
            grants: [
                'hr-editor' => ['hr.employee.edit', 'hr.employee.view'],
                'hr-admin' => ['hr.payroll.view'],
                'guest' => ['site.pages.view'],
            ],
        );

        return new RepositoryPermissionProvider(
            new SeedAssignmentRepository($roles, ['user:42' => ['hr-editor']]),
            $roles,
        );
    }
}

/**
 * A typed resource for the policy tests.
 */
final readonly class FixtureDocument extends IamResource
{
    public function __construct(
        public int $ownerId,
    ) {}
}

/**
 * One rule: the identity owns the document.
 */
final readonly class FixtureOwnerPolicy implements PolicyInterface
{
    public function __invoke(IdentityInterface $identity, FixtureDocument $resource): bool
    {
        return $identity->id() === $resource->ownerId;
    }
}
