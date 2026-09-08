<?php

declare(strict_types=1);

/**
 * The question the assignments list is asking: which edges, which slice.
 *
 * Named by both ends, because an assignment is only ever asked about from
 * one side at a time — who holds this role, what does this user hold — and
 * `q` matches the joined display text where the storage behind it can join.
 *
 * A CARRIER, nothing more: reading a request, coercing its values and
 * clamping the slice live in the caller that builds one.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\DTO;

use PHPdot\Contracts\DTO\FilterDTO;

final readonly class AssignmentsFilterDTO implements FilterDTO
{
    /**
     * @param ?string $q Free text to match against the joined user and role names, or null for no text filter
     * @param list<int> $user_ids The users to keep, or empty for every user
     * @param list<int> $role_ids The roles to keep, or empty for every role
     * @param int $page 1-based page number, at least 1
     * @param int $perPage Rows per page; the builder clamps it before it gets here
     */
    public function __construct(
        public null|string $q = null,
        public array $user_ids = [],
        public array $role_ids = [],
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
