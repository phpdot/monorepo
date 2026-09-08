<?php

declare(strict_types=1);

/**
 * The answer to a RolesFilterDTO: the matched roles and the slice they came
 * from.
 *
 * It COMPOSES the paginator — json_encode() reads the public properties, so
 * the paginator the repository built IS the `page` block on the wire, and
 * there is no second copy of `total` or `has_more` to drift out of step with
 * it. `sort` and `dir` ride beside the page, not inside it, because ordering
 * is a fact about the question, not about the slice — the fields are part of
 * the wire shape today, at null and 0, so the block a screen renders against
 * an iam list is the block it renders against any other module's list the
 * day ordering arrives here.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\DTO;

use PHPdot\Contracts\DTO\SearchDTO;
use PHPdot\Contracts\Pagination\Paginator;

final readonly class RolesSearchDTO implements SearchDTO
{
    /**
     * @param Paginator<RoleEntityDTO> $page The matched slice, and the counts describing it
     * @param ?string $sort The sorted column, or null when nothing is sorted
     * @param int $dir 1 or -1, or 0 when nothing is sorted — so the table clears its indicator
     */
    public function __construct(
        public Paginator $page,
        public null|string $sort,
        public int $dir,
    ) {}
}
