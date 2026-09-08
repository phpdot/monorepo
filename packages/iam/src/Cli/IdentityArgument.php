<?php

declare(strict_types=1);

/**
 * Parses the CLI's "type:id" identity notation ("user:42", "guest") into an
 * identity via the reconstructor — unknown types fail loudly (fail-closed),
 * never guess.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Cli;

use PHPdot\Iam\Authentication\DefaultIdentityReconstructor;
use PHPdot\Iam\Exception\InvalidCliArgumentException;
use PHPdot\Iam\Identities\Contract\Identity\IdentityInterface;
use Symfony\Component\Console\Input\InputInterface;

trait IdentityArgument
{
    use ReadsStringArguments;

    /**
     * The identity named by the given argument.
     *
     * @param InputInterface $input The command input
     * @param string $name The argument name
     *
     * @return IdentityInterface
     */
    protected function identityArgument(InputInterface $input, string $name): IdentityInterface
    {
        $raw = $this->stringArgument($input, $name);
        $parts = explode(':', $raw, 2);
        $type = $parts[0];
        $id = $parts[1] ?? null;

        if ($id === '') {
            throw new InvalidCliArgumentException(sprintf(
                "Identity [%s] carries an empty id — expected 'user:<id>', 'guest', or 'cli:cli'.",
                $raw,
            ));
        }

        $identity = new DefaultIdentityReconstructor()->reconstruct($id, $type);

        if ($identity === null) {
            throw new InvalidCliArgumentException(sprintf(
                "Unknown identity [%s] — expected 'user:<id>', 'guest', or 'cli:cli'.",
                $raw,
            ));
        }

        return $identity;
    }
}
