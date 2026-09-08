<?php

declare(strict_types=1);

/**
 * The question the permissions list is asking: what to match, which slice.
 *
 * `q` matches the key and the display name — a person looking for
 * `employees.edit-any` types a fragment of either. `status` separates the
 * declared vocabulary from what sync orphaned, because an administrator
 * sweeping up dead grants asks exactly that.
 *
 * `ids` keeps and `exclude` drops, and they are asked together: a picker
 * subtracts the selection its client is still holding, which storage has not
 * been told about, and excluding those rows after paging would answer short.
 *
 * A CARRIER, nothing more: reading a request, coercing its values and
 * clamping the slice live in the caller that builds one.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\DTO;

use PHPdot\Contracts\DTO\FilterDTO;
use PHPdot\Iam\Authorization\Enum\PermissionStatus;

final readonly class PermissionsFilterDTO implements FilterDTO
{
    /**
     * @param ?string $q Free text to match against the key and name, or null for no text filter
     * @param list<int> $ids The mirrored permission ids to keep, or empty for every permission
     * @param list<int> $exclude The mirrored permission ids to drop, or empty to drop none
     * @param ?bool $is_root Restrict to root-only or grantable, or null for both
     * @param ?PermissionStatus $status Restrict to one life stage, or null for all
     * @param int $page 1-based page number, at least 1
     * @param int $perPage Rows per page; the builder clamps it before it gets here
     */
    public function __construct(
        public null|string $q = null,
        public array $ids = [],
        public array $exclude = [],
        public null|bool $is_root = null,
        public null|PermissionStatus $status = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
