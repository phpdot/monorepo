<?php

declare(strict_types=1);

/**
 * Storage seam for the policy half of iam:permission:sync — policies are
 * CODE; storage only mirrors the inventory so the admin surface can show
 * what rules exist. Reads take a filter like every other list. Unlike
 * permissions, policies have no edges attached, so a class that vanished
 * from code is simply removed from the mirror.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Contract;

use PHPdot\Iam\Authorization\Discovery\DiscoveredPolicy;
use PHPdot\Iam\Authorization\DTO\PoliciesFilterDTO;
use PHPdot\Iam\Authorization\DTO\PoliciesSearchDTO;

interface PolicyRepositoryInterface
{
    /**
     * One page of mirrored policies matching a filter.
     *
     * @param PoliciesFilterDTO $filter The validated question
     *
     * @return PoliciesSearchDTO
     */
    public function search(PoliciesFilterDTO $filter): PoliciesSearchDTO;

    /**
     * Mirror the discovered policy inventory into storage.
     *
     * @param list<DiscoveredPolicy> $policies The scan output
     *
     * @return array{added: list<string>, removed: list<string>}
     */
    public function sync(array $policies): array;
}
