<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Cli;

use PHPdot\Iam\Authorization\Discovery\DiscoveredPermission;
use PHPdot\Iam\Authorization\Discovery\DiscoveredPolicy;
use PHPdot\Iam\Authorization\MemoryPermissionCatalog;
use PHPdot\Iam\Authorization\MemoryPolicyCatalog;
use PHPdot\Iam\Authorization\RepositoryPermissionProvider;
use PHPdot\Iam\Authorization\Role;
use PHPdot\Iam\Authorization\SeedAssignmentRepository;
use PHPdot\Iam\Authorization\SeedPermissionRepository;
use PHPdot\Iam\Authorization\SeedPolicyRepository;
use PHPdot\Iam\Authorization\SeedRoleRepository;
use PHPdot\Iam\Cli\HasCommand;
use PHPdot\Iam\Cli\PermissionListCommand;
use PHPdot\Iam\Cli\PolicyListCommand;
use PHPdot\Iam\Cli\RoleListCommand;
use PHPdot\Iam\Cli\WhyCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The read surface over one seeded world: the mirror listing (ids, free
 * text, the root-only and orphaned cuts), roles with grant counts, the
 * policy inventory, the scriptable iam:has exit contract (0 allow / 1 deny),
 * and iam:why explaining the decision chain role by role, names over ids.
 * Unknown identities fail loudly — a typo'd actor must never read as a
 * quiet deny.
 */
final class CliCommandsTest extends TestCase
{
    #[Test]
    public function permissionListShowsTheMirror(): void
    {
        $tester = new CommandTester(new PermissionListCommand($this->permissions()));

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();

        self::assertStringContainsString('hr.employee.edit', $display);
        self::assertStringContainsString('site.pages.view', $display);
        self::assertStringContainsString('root-only', $display);
    }

    #[Test]
    public function permissionListFiltersByFreeText(): void
    {
        $tester = new CommandTester(new PermissionListCommand($this->permissions()));

        self::assertSame(Command::SUCCESS, $tester->execute(['--q' => 'employee']));

        $display = $tester->getDisplay();

        self::assertStringContainsString('hr.employee.edit', $display);
        self::assertStringNotContainsString('site.pages.view', $display);
    }

    #[Test]
    public function permissionListSaysSoWhenTheFilterMatchesNothing(): void
    {
        $tester = new CommandTester(new PermissionListCommand($this->permissions()));

        self::assertSame(Command::SUCCESS, $tester->execute(['--q' => 'crm']));
        self::assertStringContainsString('No permissions matched', $tester->getDisplay());
    }

    #[Test]
    public function roleListShowsRolesWithIdsAndGrantCounts(): void
    {
        $tester = new CommandTester(new RoleListCommand($this->roles()));

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();

        self::assertStringContainsString('root', $display);
        self::assertStringContainsString('guest', $display);
        self::assertStringContainsString('hr-editor', $display);
        self::assertStringContainsString('system', $display);
    }

    #[Test]
    public function hasExitsZeroOnAllowAndOneOnDeny(): void
    {
        $tester = new CommandTester(new HasCommand($this->provider()));

        self::assertSame(Command::SUCCESS, $tester->execute(['identity' => 'user:42', 'permission' => 'hr.employee.edit']));
        self::assertStringContainsString('ALLOW', $tester->getDisplay());

        self::assertSame(Command::FAILURE, $tester->execute(['identity' => 'user:42', 'permission' => 'system.server.restart']));
        self::assertStringContainsString('DENY', $tester->getDisplay());
    }

    #[Test]
    public function guestsAnswerThroughTheGuestRole(): void
    {
        $tester = new CommandTester(new HasCommand($this->provider()));

        self::assertSame(Command::SUCCESS, $tester->execute(['identity' => 'guest', 'permission' => 'site.pages.view']));
        self::assertSame(Command::FAILURE, $tester->execute(['identity' => 'guest', 'permission' => 'hr.employee.edit']));
    }

    #[Test]
    public function rootHoldsTheEntireCatalogAsData(): void
    {
        $tester = new CommandTester(new HasCommand($this->provider()));

        self::assertSame(Command::SUCCESS, $tester->execute(['identity' => 'user:1', 'permission' => 'system.server.restart']));
    }

    #[Test]
    public function anUnknownIdentityTypeFailsWithAFailureExit(): void
    {
        $tester = new CommandTester(new HasCommand($this->provider()));

        $exit = $tester->execute(['identity' => 'alien:9', 'permission' => 'hr.employee.edit']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown identity [alien:9]', $tester->getDisplay());
    }

    #[Test]
    public function anEmptyIdentityIdFailsWithAFailureExit(): void
    {
        $tester = new CommandTester(new HasCommand($this->provider()));

        $exit = $tester->execute(['identity' => 'user:', 'permission' => 'hr.employee.edit']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('empty id', $tester->getDisplay());
    }

    #[Test]
    public function policyListShowsTheInventory(): void
    {
        $tester = new CommandTester(new PolicyListCommand(new SeedPolicyRepository(new MemoryPolicyCatalog([
            new DiscoveredPolicy('App\\Hr\\EmployeeOwnership', 'App\\Hr\\Employee'),
        ]))));

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();

        self::assertStringContainsString('EmployeeOwnership', $display);
        self::assertStringContainsString('Employee', $display);
    }

    #[Test]
    public function policyListSaysSoWhenNothingIsDiscovered(): void
    {
        $tester = new CommandTester(new PolicyListCommand(new SeedPolicyRepository(new MemoryPolicyCatalog([]))));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('No policies matched', $tester->getDisplay());
    }

    #[Test]
    public function whyExplainsAnAllowRoleByRole(): void
    {
        $tester = new CommandTester(new WhyCommand($this->provider(), $this->roles()));

        self::assertSame(Command::SUCCESS, $tester->execute(['identity' => 'user:42', 'permission' => 'hr.employee.edit']));

        $display = $tester->getDisplay();

        self::assertStringContainsString('actor user:42', $display);
        self::assertStringContainsString('hr-editor (#3)', $display);
        self::assertStringContainsString('role hr-editor grants [hr.employee.edit]', $display);
        self::assertStringContainsString('ALLOW', $display);
    }

    #[Test]
    public function whyExplainsADenyWithNoRoles(): void
    {
        $tester = new CommandTester(new WhyCommand($this->provider(), $this->roles()));

        self::assertSame(Command::FAILURE, $tester->execute(['identity' => 'user:7', 'permission' => 'hr.employee.edit']));

        $display = $tester->getDisplay();

        self::assertStringContainsString('holds no roles', $display);
        self::assertStringContainsString('DENY', $display);
    }

    #[Test]
    public function whyExplainsADenyWhenNoHeldRoleGrantsTheKey(): void
    {
        $tester = new CommandTester(new WhyCommand($this->provider(), $this->roles()));

        self::assertSame(Command::FAILURE, $tester->execute(['identity' => 'user:42', 'permission' => 'system.server.restart']));

        $display = $tester->getDisplay();

        self::assertStringContainsString('no held role grants [system.server.restart]', $display);
        self::assertStringContainsString('DENY', $display);
    }

    /**
     * The seeded catalog: two conventions, one root-only key.
     *
     * @return MemoryPermissionCatalog
     */
    private function catalog(): MemoryPermissionCatalog
    {
        return new MemoryPermissionCatalog([
            new DiscoveredPermission('hr.employee.edit', 'Edit employees', '', false, 'Fixture::class'),
            new DiscoveredPermission('site.pages.view', 'View pages', '', false, 'Fixture::class'),
            new DiscoveredPermission('system.server.restart', 'Restart the server', '', true, 'Fixture::class'),
        ]);
    }

    /**
     * The seeded mirror: the catalog dressed as storage.
     *
     * @return SeedPermissionRepository
     */
    private function permissions(): SeedPermissionRepository
    {
        return new SeedPermissionRepository($this->catalog());
    }

    /**
     * The seeded roles: root and guest (system) plus one hr editor.
     *
     * @return SeedRoleRepository
     */
    private function roles(): SeedRoleRepository
    {
        return new SeedRoleRepository(
            $this->catalog(),
            roles: [new Role(0, 'hr-editor', 'HR editor')],
            grants: [
                'hr-editor' => ['hr.employee.edit'],
                'guest' => ['site.pages.view'],
            ],
        );
    }

    /**
     * The provider over the seeded world: user 1 is root, user 42 edits HR.
     *
     * @return RepositoryPermissionProvider
     */
    private function provider(): RepositoryPermissionProvider
    {
        return new RepositoryPermissionProvider(
            new SeedAssignmentRepository($this->roles(), [
                'user:1' => ['root'],
                'user:42' => ['hr-editor'],
            ]),
            $this->roles(),
        );
    }
}
