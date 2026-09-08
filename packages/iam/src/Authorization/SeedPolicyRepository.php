<?php

declare(strict_types=1);

/**
 * Seed-backed policy inventory mirror, immutable after construction — the
 * discovered classes dressed as storage so the admin surface reads data in
 * the seed world exactly the way it will against tables. Ids are minted
 * deterministically, class-sorted, so a re-scan cannot renumber them; every
 * mirrored policy is live, because a policy that is neither declared nor
 * stored cannot be wired either. sync() throws: the mirror's write half is
 * the SQL repository's, reached only through iam:permission:sync.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization;

use PHPdot\Contracts\Pagination\Paginator;
use PHPdot\Iam\Authorization\Contract\PolicyCatalogInterface;
use PHPdot\Iam\Authorization\Contract\PolicyRepositoryInterface;
use PHPdot\Iam\Authorization\Discovery\DiscoveredPolicy;
use PHPdot\Iam\Authorization\DTO\PoliciesFilterDTO;
use PHPdot\Iam\Authorization\DTO\PoliciesSearchDTO;
use PHPdot\Iam\Authorization\DTO\PolicyEntityDTO;
use PHPdot\Iam\Exception\MemoryStorageException;

final readonly class SeedPolicyRepository implements PolicyRepositoryInterface
{
    /**
     * @var array<int, PolicyEntityDTO>
     */
    private array $byId;

    /**
     * @param PolicyCatalogInterface $catalog The discovered inventory, mirrored here
     */
    public function __construct(PolicyCatalogInterface $catalog)
    {
        $policies = $catalog->all();

        usort($policies, static fn(DiscoveredPolicy $a, DiscoveredPolicy $b): int => $a->policy <=> $b->policy);

        $byId = [];
        $next = 1;

        foreach ($policies as $policy) {
            $byId[$next] = new PolicyEntityDTO(
                id: $next,
                policy: $policy->policy,
                resource: $policy->resource,
            );

            $next++;
        }

        $this->byId = $byId;
    }

    /**
     * @inheritDoc
     */
    public function search(PoliciesFilterDTO $filter): PoliciesSearchDTO
    {
        $matched = array_values(array_filter(
            $this->byId,
            fn(PolicyEntityDTO $policy): bool => $this->matches($policy, $filter),
        ));

        $total = count($matched);
        $offset = ($filter->page - 1) * $filter->perPage;
        $slice = array_slice($matched, $offset, $filter->perPage);

        return new PoliciesSearchDTO(
            page: new Paginator($slice, $total, $filter->perPage, $filter->page),
            sort: null,
            dir: 0,
        );
    }

    /**
     * @inheritDoc
     */
    public function sync(array $policies): array
    {
        throw MemoryStorageException::for('syncing the policy inventory');
    }

    /**
     * Does this policy answer this filter?
     *
     * @param PolicyEntityDTO $policy The candidate
     * @param PoliciesFilterDTO $filter The question
     *
     * @return bool
     */
    private function matches(PolicyEntityDTO $policy, PoliciesFilterDTO $filter): bool
    {
        if ($filter->ids !== [] && !in_array($policy->id, $filter->ids, true)) {
            return false;
        }

        if ($filter->q === null) {
            return true;
        }

        $needle = mb_strtolower($filter->q);

        return str_contains(mb_strtolower($policy->policy), $needle)
            || str_contains(mb_strtolower($policy->resource), $needle);
    }
}
