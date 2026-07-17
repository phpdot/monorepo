<?php

declare(strict_types=1);

/**
 * Suggests solutions for known errors.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Contract;

use PHPdot\ErrorHandler\Solution\Solution;

interface SolutionProviderInterface
{
    /**
     * Whether this provider can suggest a solution for the given exception.
     *
     * @param \Throwable $exception
     *
     * @return bool
     */
    public function canSolve(\Throwable $exception): bool;

    /**
     * Get solutions for the given exception.
     *
     * @param \Throwable $exception
     *
     * @return list<Solution>
     */
    public function getSolutions(\Throwable $exception): array;
}
