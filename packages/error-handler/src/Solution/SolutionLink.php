<?php

declare(strict_types=1);

/**
 * A documentation link attached to a solution.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Solution;

final readonly class SolutionLink
{
    /**
     * A documentation link attached to a solution.
     *
     * @param string $label Human-readable link text
     * @param string $url Target URL
     */
    public function __construct(
        public string $label,
        public string $url,
    ) {}
}
