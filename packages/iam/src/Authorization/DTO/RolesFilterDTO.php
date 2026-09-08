<?php

declare(strict_types=1);

/**
 * The question the roles list is asking: what to match, which slice.
 *
 * Same shape and same rules as every module filter in the platform: `page`
 * and `limit` are reserved, `q` is free text, and every other filter is
 * named. No `sort` and no `direction` yet — ordering is the repository's own
 * (id-tiebroken, so a page boundary is stable), and the fields join this
 * carrier the day ordering becomes a question worth asking.
 *
 * A CARRIER, nothing more. Reading a request, coercing its values and
 * clamping the slice live in the caller that builds one — this class holds
 * what was decided there, and decides nothing itself.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\DTO;

use PHPdot\Contracts\DTO\FilterDTO;

final readonly class RolesFilterDTO implements FilterDTO
{
    /**
     * @param ?string $q Free text to match against the name, or null for no text filter
     * @param ?string $name The exact name to keep — the uniqueness question, asked exactly
     * @param list<int> $ids The role ids to keep, or empty for every role
     * @param ?bool $is_system Restrict to reserved or administrable roles, or null for both
     * @param bool $_grants Attach each role's grant count
     * @param int $page 1-based page number, at least 1
     * @param int $perPage Rows per page; the builder clamps it before it gets here
     */
    public function __construct(
        public null|string $q = null,
        public null|string $name = null,
        public array $ids = [],
        public null|bool $is_system = null,
        public bool $_grants = false,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
