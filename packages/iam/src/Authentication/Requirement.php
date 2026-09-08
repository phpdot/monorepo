<?php

declare(strict_types=1);

/**
 * A single proof the policy demands, named by the factor that satisfies it
 * (matching some AuthenticationStageInterface::factor()), with optional per-requirement
 * hints passed through to that stage.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication;

use PHPdot\Iam\Exception\InvalidRequirementException;

final readonly class Requirement
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public string $factor,
        public array $params = [],
    ) {
        if ($factor === '') {
            throw new InvalidRequirementException('Requirement factor must not be empty.');
        }
    }
}
