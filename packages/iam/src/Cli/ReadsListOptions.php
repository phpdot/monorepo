<?php

declare(strict_types=1);

/**
 * Reading the shared list options — q, page, limit — the same way in every
 * list command. DEGRADES rather than rejecting, the filter law: `--page
 * nonsense` becomes page 1 and `--limit 0` becomes 1, because a stale
 * command from a shell history deserves a sane answer, not a traceback. The
 * limit is clamped to a hard ceiling: a terminal is a caller like any other,
 * and an unbounded page is an unbounded answer.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Cli;

use Symfony\Component\Console\Input\InputInterface;

trait ReadsListOptions
{
    private const int MAX_CLI_PER_PAGE = 100;

    /**
     * A text option, or null when it is absent or empty.
     *
     * @param InputInterface $input The console input
     * @param string $name The option name
     *
     * @return string|null
     */
    protected function textOption(InputInterface $input, string $name): null|string
    {
        $value = $input->getOption($name);

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * The 1-based page option, degrading to page 1.
     *
     * @param InputInterface $input The console input
     *
     * @return int
     */
    protected function pageOption(InputInterface $input): int
    {
        return max(1, $this->numberOption($input, 'page') ?? 1);
    }

    /**
     * The rows-per-page option, clamped to the ceiling.
     *
     * @param InputInterface $input The console input
     *
     * @return int
     */
    protected function limitOption(InputInterface $input): int
    {
        $asked = $this->numberOption($input, 'limit') ?? self::MAX_CLI_PER_PAGE;

        return min(self::MAX_CLI_PER_PAGE, max(1, $asked));
    }

    /**
     * A numeric option, or null when it is absent or not a number.
     *
     * @param InputInterface $input The console input
     * @param string $name The option name
     *
     * @return int|null
     */
    private function numberOption(InputInterface $input, string $name): null|int
    {
        $value = $input->getOption($name);

        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }
}
