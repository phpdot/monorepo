<?php

declare(strict_types=1);

/**
 * The question the policies list is asking: what to match, which slice.
 *
 * `q` matches the class and the resource — a person asking "what judges a
 * channel" types the resource's short name.
 *
 * A CARRIER, nothing more: reading a request, coercing its values and
 * clamping the slice live in the caller that builds one.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\DTO;

use PHPdot\Contracts\DTO\FilterDTO;

final readonly class PoliciesFilterDTO implements FilterDTO
{
    /**
     * @param ?string $q Free text to match against the class and resource, or null for no text filter
     * @param list<int> $ids The mirrored policy ids to keep, or empty for every policy
     * @param int $page 1-based page number, at least 1
     * @param int $perPage Rows per page; the builder clamps it before it gets here
     */
    public function __construct(
        public null|string $q = null,
        public array $ids = [],
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
