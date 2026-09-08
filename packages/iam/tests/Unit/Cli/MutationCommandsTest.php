<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Cli;

use PHPdot\Contracts\Pagination\Paginator;
use PHPdot\Iam\Authorization\Contract\PermissionRepositoryInterface;
use PHPdot\Iam\Authorization\Contract\PolicyRepositoryInterface;
use PHPdot\Iam\Authorization\Contract\RoleManagerInterface;
use PHPdot\Iam\Authorization\Discovery\DiscoveredPermission;
use PHPdot\Iam\Authorization\Discovery\DiscoveredPolicy;
use PHPdot\Iam\Authorization\Discovery\DiscoveryPaths;
use PHPdot\Iam\Authorization\Discovery\IamScan;
use PHPdot\Iam\Authorization\DTO\AssignmentSaveDTO;
use PHPdot\Iam\Authorization\DTO\GrantSaveDTO;
use PHPdot\Iam\Authorization\DTO\PermissionEntityDTO;
use PHPdot\Iam\Authorization\DTO\PermissionsFilterDTO;
use PHPdot\Iam\Authorization\DTO\PermissionsSearchDTO;
use PHPdot\Iam\Authorization\DTO\PoliciesFilterDTO;
use PHPdot\Iam\Authorization\DTO\PoliciesSearchDTO;
use PHPdot\Iam\Authorization\DTO\RoleSaveDTO;
use PHPdot\Iam\Cli\AssignCommand;
use PHPdot\Iam\Cli\PermissionSyncCommand;
use PHPdot\Iam\Cli\RoleCreateCommand;
use PHPdot\Iam\Cli\RoleDeleteCommand;
use PHPdot\Iam\Cli\RoleGrantCommand;
use PHPdot\Iam\Cli\UnassignCommand;
use PHPdot\Iam\Exception\SystemRoleException;
use PHPdot\Iam\Exception\UnknownPermissionException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

/**
 * The mutation CLI is a thin, honest skin over RoleManagerInterface: ids and
 * carriers reach the manager verbatim, domain refusals become error text +
 * exit 1 (never a stack trace), and iam:permission:sync reports what the
 * repository did.
 */
final class MutationCommandsTest extends TestCase
{
    #[Test]
    public function createReachesTheManagerAndExitsZero(): void
    {
        $manager = $this->world();
        $tester = new CommandTester(new RoleCreateCommand($manager));

        $exit = $tester->execute(['name' => 'hr-editor', 'description' => 'Edits.']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame([['saveRole', 'hr-editor', 'Edits.']], $manager->calls);
        self::assertStringContainsString('created', $tester->getDisplay());
    }

    #[Test]
    public function aDomainRefusalIsAnErrorMessageAndExitOne(): void
    {
        $manager = $this->world(throw: new SystemRoleException('The [root] role is a system role and cannot be deleted.'));
        $tester = new CommandTester(new RoleDeleteCommand($manager));

        $exit = $tester->execute(['role' => '1']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('system role', $tester->getDisplay());
    }

    #[Test]
    public function aNonIdArgumentIsRefusedLoudly(): void
    {
        $manager = $this->world();
        $tester = new CommandTester(new RoleDeleteCommand($manager));

        $exit = $tester->execute(['role' => 'root']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('positive id', $tester->getDisplay());
        self::assertSame([], $manager->calls, 'a name-shaped argument never reaches the manager');
    }

    #[Test]
    public function grantPassesBothIdsThrough(): void
    {
        $manager = $this->world();
        $tester = new CommandTester(new RoleGrantCommand($manager));

        $exit = $tester->execute(['role' => '3', 'permission' => '1']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame([['grant', 3, 1]], $manager->calls);
    }

    #[Test]
    public function anUnknownPermissionRefusalExitsOne(): void
    {
        $manager = $this->world(throw: UnknownPermissionException::forId(9));
        $tester = new CommandTester(new RoleGrantCommand($manager));

        self::assertSame(Command::FAILURE, $tester->execute(['role' => '3', 'permission' => '9']));
        self::assertStringContainsString('Permission [9]', $tester->getDisplay());
    }

    #[Test]
    public function assignAndUnassignParseTheIdentityNotation(): void
    {
        $manager = $this->world();

        self::assertSame(Command::SUCCESS, new CommandTester(new AssignCommand($manager))
            ->execute(['identity' => 'user:42', 'role' => '3']));
        self::assertSame(Command::SUCCESS, new CommandTester(new UnassignCommand($manager))
            ->execute(['identity' => 'user:42', 'role' => '3']));

        self::assertSame('assign', $manager->calls[0][0]);
        self::assertSame('user:42', $manager->calls[0][1]);
        self::assertSame(3, $manager->calls[0][2]);
        self::assertSame('unassign', $manager->calls[1][0]);
    }

    #[Test]
    public function syncScansOnceAndMirrorsBothHalves(): void
    {
        $permissions = new class implements PermissionRepositoryInterface {
            /**
             * @var list<DiscoveredPermission>|null
             */
            public null|array $received = null;

            public function sync(array $permissions): array
            {
                $this->received = $permissions;

                return [
                    'added' => array_map(static fn($p) => $p->key, $permissions),
                    'updated' => [],
                    'orphaned' => ['old.gone.key'],
                ];
            }

            public function search(PermissionsFilterDTO $filter): PermissionsSearchDTO
            {
                return new PermissionsSearchDTO(new Paginator([], 0, 20, 1), null, 0);
            }

            public function find(int $id): null|PermissionEntityDTO
            {
                return null;
            }

            public function exists(PermissionsFilterDTO $filter): bool
            {
                return false;
            }
        };

        $policies = new class implements PolicyRepositoryInterface {
            /**
             * @var list<DiscoveredPolicy>|null
             */
            public null|array $received = null;

            public function sync(array $policies): array
            {
                $this->received = $policies;

                return ['added' => array_map(static fn($p) => $p->policy, $policies), 'removed' => []];
            }

            public function search(PoliciesFilterDTO $filter): PoliciesSearchDTO
            {
                return new PoliciesSearchDTO(new Paginator([], 0, 20, 1), null, 0);
            }
        };

        $tester = new CommandTester(new PermissionSyncCommand(
            new IamScan(),
            new DiscoveryPaths([__DIR__ . '/../../Fixtures/Scan/Valid']),
            $permissions,
            $policies,
        ));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertCount(2, $permissions->received ?? [], 'both fixture permissions reached the mirror');
        self::assertCount(2, $policies->received ?? [], 'both fixture policies reached the mirror');

        $display = $tester->getDisplay();

        self::assertStringContainsString('2 added, 0 updated, 1 orphaned', $display);
        self::assertStringContainsString('old.gone.key', $display);
        self::assertStringContainsString('Policies: 2 added, 0 removed', $display);
    }

    /**
     * A recording (or throwing) RoleManagerInterface double.
     *
     * @param Throwable|null $throw Thrown by every mutation when set
     *
     * @return RoleManagerInterface&object{calls: list<list<mixed>>}
     */
    private function world(null|Throwable $throw = null): RoleManagerInterface
    {
        return new class ($throw) implements RoleManagerInterface {
            /**
             * @var list<list<mixed>>
             */
            public array $calls = [];

            public function __construct(private readonly null|Throwable $throw) {}

            public function saveRole(RoleSaveDTO $role): void
            {
                $this->refuse();
                $this->calls[] = ['saveRole', $role->name, $role->description];
            }

            public function deleteRole(int $id): void
            {
                $this->refuse();
                $this->calls[] = ['deleteRole', $id];
            }

            public function grant(GrantSaveDTO $grant): void
            {
                $this->refuse();
                $this->calls[] = ['grant', $grant->role_id, $grant->permission_id];
            }

            public function revoke(GrantSaveDTO $grant): void
            {
                $this->refuse();
                $this->calls[] = ['revoke', $grant->role_id, $grant->permission_id];
            }

            public function assign(AssignmentSaveDTO $assignment): void
            {
                $this->refuse();
                $this->calls[] = [
                    'assign',
                    $assignment->identity->type() . ':' . (string) ($assignment->identity->id() ?? ''),
                    $assignment->role_id,
                ];
            }

            public function unassign(AssignmentSaveDTO $assignment): void
            {
                $this->refuse();
                $this->calls[] = [
                    'unassign',
                    $assignment->identity->type() . ':' . (string) ($assignment->identity->id() ?? ''),
                    $assignment->role_id,
                ];
            }

            private function refuse(): void
            {
                if ($this->throw !== null) {
                    throw $this->throw;
                }
            }
        };
    }
}
