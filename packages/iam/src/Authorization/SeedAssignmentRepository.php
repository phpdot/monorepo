<?php

declare(strict_types=1);

/**
 * Seed-backed identity ↔ role assignments, immutable after construction.
 * Wiring speaks names — what a person writes in configuration — and this
 * repository resolves them against the role seed's minted ids, because an
 * edge is ids all the way down. Guests always hold the reserved guest role —
 * public capability is data on that role's grants, never code. Mutations
 * throw until the SQL repositories arrive.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization;

use PHPdot\Contracts\Pagination\Paginator;
use PHPdot\Iam\Authorization\Contract\AssignmentRepositoryInterface;
use PHPdot\Iam\Authorization\DTO\AssignmentEntityDTO;
use PHPdot\Iam\Authorization\DTO\AssignmentSaveDTO;
use PHPdot\Iam\Authorization\DTO\AssignmentsFilterDTO;
use PHPdot\Iam\Authorization\DTO\AssignmentsSearchDTO;
use PHPdot\Iam\Authorization\DTO\RolesFilterDTO;
use PHPdot\Iam\Exception\MemoryStorageException;
use PHPdot\Iam\Exception\UnknownRoleException;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;
use PHPdot\Iam\Identities\Types\GuestIdentity;

final readonly class SeedAssignmentRepository implements AssignmentRepositoryInterface
{
    /**
     * @var array<string, list<int>> "type:id" => role ids
     */
    private array $assignments;

    /**
     * @var array<int, string> role id => name, for the text a search matches
     */
    private array $roleNames;

    /**
     * @param SeedRoleRepository $roles The role seed, which owns the minted ids
     * @param array<string, list<string>> $assignments "type:id" => role names
     */
    public function __construct(
        private SeedRoleRepository $roles,
        array $assignments = [],
    ) {
        $resolved = [];

        foreach ($assignments as $subject => $names) {
            $ids = [];

            foreach ($names as $name) {
                $id = $roles->idOf($name) ?? throw UnknownRoleException::named($name);

                $ids[] = $id;
            }

            $resolved[$subject] = $ids;
        }

        $this->assignments = $resolved;
        $roleNames = [];

        foreach ($roles->lookups(new RolesFilterDTO()) as $lookup) {
            $roleNames[$lookup->id] = $lookup->name;
        }

        $this->roleNames = $roleNames;
    }

    /**
     * @inheritDoc
     */
    public function rolesFor(IdentityInterface $identity): array
    {
        if ($identity instanceof GuestIdentity) {
            $guestId = $this->roles->idOf('guest');

            return $guestId === null ? [] : [$guestId];
        }

        return $this->assignments[$identity->type() . ':' . (string) ($identity->id() ?? '')] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function search(AssignmentsFilterDTO $filter): AssignmentsSearchDTO
    {
        $matched = [];

        foreach ($this->assignments as $subject => $roleIds) {
            $userId = $this->userId($subject);

            if ($userId === null) {
                continue;
            }

            foreach ($roleIds as $roleId) {
                if ($this->matches($userId, $roleId, $subject, $filter)) {
                    $matched[] = new AssignmentEntityDTO(user_id: $userId, role_id: $roleId);
                }
            }
        }

        $total = count($matched);
        $offset = ($filter->page - 1) * $filter->perPage;
        $slice = array_slice($matched, $offset, $filter->perPage);

        return new AssignmentsSearchDTO(
            page: new Paginator($slice, $total, $filter->perPage, $filter->page),
            sort: null,
            dir: 0,
        );
    }

    /**
     * @inheritDoc
     */
    public function assign(AssignmentSaveDTO $assignment): void
    {
        throw MemoryStorageException::for('assigning a role');
    }

    /**
     * @inheritDoc
     */
    public function unassign(AssignmentSaveDTO $assignment): void
    {
        throw MemoryStorageException::for('unassigning a role');
    }

    /**
     * The user id behind a wiring subject, or null when the edge is not a
     * user's — search answers storage's shape, and the table holds users.
     *
     * @param string $subject The wiring key, "type:id"
     *
     * @return int|null
     */
    private function userId(string $subject): null|int
    {
        $parts = explode(':', $subject, 2);

        if ($parts[0] !== 'user' || !isset($parts[1]) || !ctype_digit($parts[1])) {
            return null;
        }

        return (int) $parts[1];
    }

    /**
     * Does this edge answer this filter?
     *
     * @param int $userId The user holding the role
     * @param int $roleId The role they hold
     * @param string $subject The wiring key — the text a free-text search matches
     * @param AssignmentsFilterDTO $filter The question
     *
     * @return bool
     */
    private function matches(int $userId, int $roleId, string $subject, AssignmentsFilterDTO $filter): bool
    {
        if ($filter->user_ids !== [] && !in_array($userId, $filter->user_ids, true)) {
            return false;
        }

        if ($filter->role_ids !== [] && !in_array($roleId, $filter->role_ids, true)) {
            return false;
        }

        if ($filter->q === null) {
            return true;
        }

        $needle = mb_strtolower($filter->q);
        $roleName = $this->roleNames[$roleId] ?? '';

        return str_contains(mb_strtolower($subject), $needle) || str_contains(mb_strtolower($roleName), $needle);
    }
}
