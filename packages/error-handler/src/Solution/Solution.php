<?php

declare(strict_types=1);

/**
 * A suggested fix for a known error.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Solution;

final readonly class Solution
{
    /**
     * A suggested fix for a known error, with optional documentation links.
     *
     * @param string $title Short title (e.g. "Class not found")
     * @param string $description Explanation of the fix
     * @param list<SolutionLink> $links Relevant documentation links
     */
    public function __construct(
        public string $title,
        public string $description,
        public array $links = [],
    ) {}
}
