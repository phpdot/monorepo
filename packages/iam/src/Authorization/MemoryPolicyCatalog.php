<?php

declare(strict_types=1);

/**
 * The policy inventory, immutable after construction — built once from the
 * scan (pre-fork; workers inherit the frozen copy, correct for data that
 * only changes with a deploy).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization;

use PHPdot\Iam\Authorization\Contract\PolicyCatalogInterface;
use PHPdot\Iam\Authorization\Discovery\DiscoveredPolicy;

final readonly class MemoryPolicyCatalog implements PolicyCatalogInterface
{
    /**
     * @param list<DiscoveredPolicy> $policies The scan output
     */
    public function __construct(
        private array $policies,
    ) {}

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->policies;
    }
}
