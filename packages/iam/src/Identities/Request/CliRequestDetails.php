<?php

declare(strict_types=1);

/**
 * Console request context: the command being run. CLI runs have no peer IP or
 * user agent; the command name is the identifying context.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Identities\Request;

use PHPdot\Iam\Identities\Contract\Context\RequestDetailsInterface;

final readonly class CliRequestDetails implements RequestDetailsInterface
{
    public function __construct(
        private string $command,
    ) {}

    public function channel(): string
    {
        return 'cli';
    }

    public function command(): string
    {
        return $this->command;
    }
}
