<?php

declare(strict_types=1);

/**
 * The seam the application implements — the policy decision point, separated
 * from the enforcement engine on purpose.
 *
 * Given the context (identity, completed factors, request attributes) it returns
 * the ORDERED set of proofs still required. All authentication intelligence
 * lives in the app's implementation. iam ships no production policy — only
 * AllowAfterIdentificationPolicy for tests and single-factor apps.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication\Contract;

use PHPdot\Iam\Authentication\AuthenticationContext;
use PHPdot\Iam\Authentication\RequirementSet;

interface AuthenticationPolicyInterface
{
    public function requirementsFor(AuthenticationContext $context): RequirementSet;
}
