<?php

declare(strict_types=1);

/**
 * Input to the AuthenticationPolicyInterface: who has been identified, which factors are
 * already satisfied, and the request context the application supplies
 * (ip, country, device, time, the user's usual country, …).
 *
 * iam never gathers context itself — the HTTP/framework layer builds `attributes`
 * and passes it into the attempt. `attributes` is an open string-keyed map on
 * purpose: the core cannot enumerate what a policy might need.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication;

use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;

final readonly class AuthenticationContext
{
    /**
     * @param list<string> $completedFactors
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public IdentityInterface $identity,
        public array $completedFactors,
        public array $attributes = [],
    ) {}
}
