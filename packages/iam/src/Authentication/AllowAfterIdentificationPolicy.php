<?php

declare(strict_types=1);

/**
 * Trivial policy: identification alone is enough — it never requires a second
 * factor.
 *
 * A starting point for tests and single-factor apps, NOT a production default
 * to rely on. It is deliberately not container-bound: an application must choose
 * its policy explicitly, so a security rule never defaults into place silently.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication;

use PHPdot\Iam\Authentication\Contract\AuthenticationPolicyInterface;

final class AllowAfterIdentificationPolicy implements AuthenticationPolicyInterface
{
    public function requirementsFor(AuthenticationContext $context): RequirementSet
    {
        return RequirementSet::none();
    }
}
