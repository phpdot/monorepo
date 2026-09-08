<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authorization;

use PHPdot\Iam\Authorization\Discovery\DiscoveredPermission;
use PHPdot\Iam\Authorization\DTO\AssignmentSaveDTO;
use PHPdot\Iam\Authorization\DTO\AssignmentsFilterDTO;
use PHPdot\Iam\Authorization\DTO\GrantSaveDTO;
use PHPdot\Iam\Authorization\DTO\PermissionEntityDTO;
use PHPdot\Iam\Authorization\DTO\PermissionsFilterDTO;
use PHPdot\Iam\Authorization\DTO\RoleSaveDTO;
use PHPdot\Iam\Authorization\DTO\RolesFilterDTO;
use PHPdot\Iam\Authorization\Enum\PermissionStatus;
use PHPdot\Iam\Authorization\MemoryPermissionCatalog;
use PHPdot\Iam\Authorization\Role;
use PHPdot\Iam\Authorization\SeedAssignmentRepository;
use PHPdot\Iam\Authorization\SeedPermissionRepository;
use PHPdot\Iam\Authorization\SeedRoleRepository;
use PHPdot\Iam\Exception\DuplicateRoleException;
use PHPdot\Iam\Exception\InvalidRoleNameException;
use PHPdot\Iam\Exception\MemoryStorageException;
use PHPdot\Iam\Exception\SystemRoleException;
use PHPdot\Iam\Exception\UnknownRoleException;
use PHPdot\Iam\Identities\Types\GuestIdentity;
use PHPdot\Iam\Identities\Types\UserIdentity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The seed-backed storage laws: the system roles always exist with the
 * reserved ids, root holds the ENTIRE catalog as data (never a code bypass),
 * guests always hold the reserved guest role, searches page and filter the
 * seeded world, the permission mirror dresses the catalog as storage with
 * deterministic ids, and every mutation throws — an in-memory write would be
 * worker-local and lost on reload.
 */
final class SeedRepositoriesTest extends TestCase
{
    #[Test]
    public function theSystemRolesAlwaysExistWithTheReservedIds(): void
    {
        $repository = new SeedRoleRepository(new MemoryPermissionCatalog([]));

        $root = $repository->findOne(new RolesFilterDTO(ids: [1]));
        $guest = $repository->findOne(new RolesFilterDTO(ids: [2]));

        self::assertNotNull($root);
        self::assertNotNull($guest);
        self::assertSame('root', $root->name);
        self::assertSame('guest', $guest->name);
        self::assertTrue($root->is_system);
        self::assertTrue($guest->is_system);
    }

    #[Test]
    public function seededRolesTakeMintedIdsInWiringOrder(): void
    {
        $repository = new SeedRoleRepository(
            new MemoryPermissionCatalog([]),
            roles: [new Role(0, 'hr-editor', 'HR editor'), new Role(0, 'site-editor')],
        );

        self::assertSame('hr-editor', $repository->findOne(new RolesFilterDTO(ids: [3]))?->name);
        self::assertSame('site-editor', $repository->findOne(new RolesFilterDTO(ids: [4]))?->name);
        self::assertNull($repository->findOne(new RolesFilterDTO(ids: [5])));
    }

    #[Test]
    public function rootIsGrantedTheEntireCatalogAtConstruction(): void
    {
        $repository = new SeedRoleRepository(new MemoryPermissionCatalog([
            new DiscoveredPermission('hr.employee.edit', 'Edit employees', '', false, 'Fixture::class'),
            new DiscoveredPermission('system.server.restart', 'Restart the server', '', true, 'Fixture::class'),
        ]));

        self::assertSame(
            ['hr.employee.edit', 'system.server.restart'],
            $repository->permissionsOf(1),
        );
    }

    #[Test]
    public function seededGrantsResolveByNameAndReadById(): void
    {
        $repository = new SeedRoleRepository(
            new MemoryPermissionCatalog([]),
            roles: [new Role(0, 'hr-editor', 'HR editor')],
            grants: ['hr-editor' => ['hr.employee.edit'], 'guest' => ['site.pages.view']],
        );

        self::assertSame(['hr.employee.edit'], $repository->permissionsOf(3));
        self::assertSame(['site.pages.view'], $repository->permissionsOf(2));
        self::assertSame([], $repository->permissionsOf(99));
    }

    #[Test]
    public function searchFiltersTextAndPages(): void
    {
        $repository = new SeedRoleRepository(
            new MemoryPermissionCatalog([]),
            roles: [
                new Role(0, 'hr-editor'),
                new Role(0, 'hr-manager'),
                new Role(0, 'site-editor'),
            ],
        );

        $matched = $repository->search(new RolesFilterDTO(q: 'hr', page: 1, perPage: 2));

        self::assertSame(2, $matched->page->total);
        self::assertSame(['hr-editor', 'hr-manager'], array_map(
            static fn($role): string => $role->name,
            $matched->page->items,
        ));
        self::assertFalse($matched->page->has_more);
    }

    #[Test]
    public function searchSlicesThePageAndReportsMore(): void
    {
        $repository = new SeedRoleRepository(
            new MemoryPermissionCatalog([]),
            roles: [new Role(0, 'one'), new Role(0, 'two'), new Role(0, 'three')],
        );

        $matched = $repository->search(new RolesFilterDTO(page: 2, perPage: 2));

        self::assertSame(5, $matched->page->total);
        self::assertSame(['one', 'two'], array_map(
            static fn($role): string => $role->name,
            $matched->page->items,
        ));
        self::assertTrue($matched->page->has_more);
        self::assertNull($matched->sort);
        self::assertSame(0, $matched->dir);
    }

    #[Test]
    public function grantCountsAttachWhenAsked(): void
    {
        $repository = new SeedRoleRepository(
            new MemoryPermissionCatalog([]),
            roles: [new Role(0, 'hr-editor')],
            grants: ['hr-editor' => ['hr.employee.edit', 'hr.employee.view']],
        );

        $matched = $repository->search(new RolesFilterDTO(_grants: true));

        $counts = [];

        foreach ($matched->page->items as $role) {
            $counts[$role->name] = $role->_grants;
        }

        self::assertSame(['root' => 0, 'guest' => 0, 'hr-editor' => 2], $counts);
    }

    #[Test]
    public function lookupsAnswerIdAndNameOnly(): void
    {
        $repository = new SeedRoleRepository(
            new MemoryPermissionCatalog([]),
            roles: [new Role(0, 'hr-editor')],
        );

        $lookups = $repository->lookups(new RolesFilterDTO(q: 'hr'));

        self::assertCount(1, $lookups);
        self::assertSame(3, $lookups[0]->id);
        self::assertSame('hr-editor', $lookups[0]->name);
    }

    #[Test]
    public function guestsAlwaysHoldTheGuestRoleById(): void
    {
        $roles = new SeedRoleRepository(new MemoryPermissionCatalog([]));
        $repository = new SeedAssignmentRepository($roles, ['user:42' => ['root']]);

        self::assertSame([2], $repository->rolesFor(new GuestIdentity()));
        self::assertSame([1], $repository->rolesFor(new UserIdentity(42)));
        self::assertSame([], $repository->rolesFor(new UserIdentity(7)));
    }

    #[Test]
    public function assignmentsSearchFiltersBothEnds(): void
    {
        $roles = new SeedRoleRepository(
            new MemoryPermissionCatalog([]),
            roles: [new Role(0, 'hr-editor')],
        );
        $repository = new SeedAssignmentRepository($roles, [
            'user:42' => ['hr-editor'],
            'user:7' => ['root', 'guest'],
        ]);

        $byRole = $repository->search(new AssignmentsFilterDTO(role_ids: [1, 2]));

        self::assertSame(2, $byRole->page->total);

        $byUser = $repository->search(new AssignmentsFilterDTO(user_ids: [7]));

        self::assertSame(2, $byUser->page->total);

        $byText = $repository->search(new AssignmentsFilterDTO(q: 'hr-editor'));

        self::assertSame(1, $byText->page->total);
    }

    #[Test]
    public function thePermissionMirrorDressesTheCatalogAsStorage(): void
    {
        $repository = new SeedPermissionRepository(new MemoryPermissionCatalog([
            new DiscoveredPermission('system.server.restart', 'Restart', '', true, 'Fixture::class'),
            new DiscoveredPermission('hr.employee.edit', 'Edit employees', '', false, 'Fixture::class'),
        ]));

        $first = $repository->find(1);

        self::assertNotNull($first);
        self::assertSame('hr.employee.edit', $first->key, 'ids are minted key-sorted, so a re-scan cannot renumber');
        self::assertFalse($first->is_root);
        self::assertSame(PermissionStatus::Active, $first->status);

        $rootOnly = $repository->search(new PermissionsFilterDTO(is_root: true));

        self::assertSame(1, $rootOnly->page->total);
        self::assertSame('system.server.restart', $rootOnly->page->items[0]->key);
    }

    #[Test]
    public function excludeDropsRowsAndOutranksTheIdsItOverlaps(): void
    {
        $repository = new SeedPermissionRepository(new MemoryPermissionCatalog([
            new DiscoveredPermission('hr.employee.edit', 'Edit employees', '', false, 'Fixture::class'),
            new DiscoveredPermission('hr.employee.view', 'View employees', '', false, 'Fixture::class'),
            new DiscoveredPermission('system.server.restart', 'Restart', '', true, 'Fixture::class'),
        ]));

        self::assertSame(3, $repository->search(new PermissionsFilterDTO())->page->total);

        $dropped = $repository->search(new PermissionsFilterDTO(exclude: [1]));

        self::assertSame(2, $dropped->page->total);
        self::assertSame(
            ['hr.employee.view', 'system.server.restart'],
            array_map(static fn(PermissionEntityDTO $permission): string => $permission->key, $dropped->page->items),
        );

        $both = $repository->search(new PermissionsFilterDTO(ids: [1, 2], exclude: [2]));

        self::assertSame(1, $both->page->total);
        self::assertSame('hr.employee.edit', $both->page->items[0]->key);
        self::assertFalse($repository->exists(new PermissionsFilterDTO(ids: [1], exclude: [1])));
    }

    #[Test]
    public function everyRoleMutationThrows(): void
    {
        $repository = new SeedRoleRepository(new MemoryPermissionCatalog([]));

        foreach ([
            static fn() => $repository->save(new RoleSaveDTO(null, 'hr-editor')),
            static fn() => $repository->delete(2),
            static fn() => $repository->grant(new GrantSaveDTO(2, 1)),
            static fn() => $repository->revoke(new GrantSaveDTO(2, 1)),
        ] as $mutation) {
            try {
                $mutation();
                self::fail('the mutation should have thrown');
            } catch (MemoryStorageException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function everyAssignmentMutationThrows(): void
    {
        $repository = new SeedAssignmentRepository(new SeedRoleRepository(new MemoryPermissionCatalog([])));

        foreach ([
            static fn() => $repository->assign(new AssignmentSaveDTO(new UserIdentity(42), 3)),
            static fn() => $repository->unassign(new AssignmentSaveDTO(new UserIdentity(42), 3)),
        ] as $mutation) {
            try {
                $mutation();
                self::fail('the mutation should have thrown');
            } catch (MemoryStorageException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function theMirrorRefusesToSyncInMemory(): void
    {
        $repository = new SeedPermissionRepository(new MemoryPermissionCatalog([]));

        $this->expectException(MemoryStorageException::class);

        $repository->sync([]);
    }

    #[Test]
    public function wiringAGrantForAnUnknownRoleIsRefused(): void
    {
        $this->expectException(UnknownRoleException::class);

        new SeedRoleRepository(new MemoryPermissionCatalog([]), grants: ['ghost' => ['hr.employee.edit']]);
    }

    #[Test]
    public function seedingADuplicateRoleNameIsRefused(): void
    {
        $this->expectException(DuplicateRoleException::class);

        new SeedRoleRepository(new MemoryPermissionCatalog([]), [
            new Role(0, 'hr-editor'),
            new Role(0, 'hr-editor'),
        ]);
    }

    #[Test]
    public function seedingAReservedRoleNameIsRefused(): void
    {
        $this->expectException(SystemRoleException::class);

        new SeedRoleRepository(new MemoryPermissionCatalog([]), [new Role(0, 'root', 'A host override.')]);
    }

    /**
     * @return list<array{string}>
     */
    public static function invalidNames(): array
    {
        return [[''], [' padded'], ['padded '], [str_repeat('x', 65)]];
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function anInvalidNameIsRejectedAtConstruction(string $name): void
    {
        $this->expectException(InvalidRoleNameException::class);

        new Role(1, $name, 'Broken');
    }

    #[Test]
    public function validNamesConstruct(): void
    {
        self::assertSame('hr-editor', new Role(1, 'hr-editor', 'HR editor')->name);
        self::assertSame('HR Editor', new Role(2, 'HR Editor')->name);
    }
}
