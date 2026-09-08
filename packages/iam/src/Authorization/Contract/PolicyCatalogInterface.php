<?php

declare(strict_types=1);

/**
 * The policy inventory — every discovered rule, for the admin surface
 * (iam:policy:list, the admin UI later). Read-only by design: policies are
 * code, the catalog only reports what the scan found. Dispatch NEVER
 * consults this — the developer names the policy class at the call site.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Contract;

use PHPdot\Iam\Authorization\Discovery\DiscoveredPolicy;

interface PolicyCatalogInterface
{
    /**
     * Every discovered policy.
     *
     * @return list<DiscoveredPolicy>
     */
    public function all(): array;
}
