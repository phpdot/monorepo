<?php

declare(strict_types=1);

/**
 * Thrown when a task is referenced by a name that was never defined.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Task;

use PHPdot\Bun\Exception\BunException;

final class UnknownTaskException extends \RuntimeException implements BunException
{
    /**
     * @param list<string> $available
     * @param string $name
     */
    public function __construct(string $name, array $available)
    {
        parent::__construct(sprintf(
            'Unknown task "%s". Defined tasks: %s',
            $name,
            $available === [] ? '(none)' : implode(', ', $available),
        ));
    }
}
