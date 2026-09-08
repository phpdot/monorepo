<?php

declare(strict_types=1);

/**
 * Reading raw console input as the typed values the contracts take. A
 * console argument is a string by nature; these are the narrowings — a
 * missing or malformed value fails loudly as an invalid argument rather than
 * coercing into a wrong id.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Cli;

use PHPdot\Iam\Exception\InvalidCliArgumentException;
use Symfony\Component\Console\Input\InputInterface;

trait ReadsStringArguments
{
    protected function stringArgument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);

        if (!is_string($value)) {
            throw new InvalidCliArgumentException(sprintf('The [%s] argument must be a string.', $name));
        }

        return $value;
    }

    /**
     * A storage id as a positive integer — the one address the contracts
     * take, and a non-number names nothing.
     *
     * @param InputInterface $input The console input
     * @param string $name The argument name
     *
     * @return int
     */
    protected function idArgument(InputInterface $input, string $name): int
    {
        $raw = $this->stringArgument($input, $name);

        if (!ctype_digit($raw) || $raw === '0') {
            throw new InvalidCliArgumentException(sprintf('The [%s] argument must be a positive id, [%s] given.', $name, $raw));
        }

        return (int) $raw;
    }
}
