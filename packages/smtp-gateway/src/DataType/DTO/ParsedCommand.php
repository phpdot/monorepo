<?php

declare(strict_types=1);

/**
 * Parsed SMTP command from client.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\DataType\DTO;

use PHPdot\Mail\Gateway\DataType\Enum\SmtpCommand;

/**
 * Represents a parsed SMTP command with its verb and argument.
 */
readonly class ParsedCommand
{
    public function __construct(
        public SmtpCommand $verb,
        public string $argument,
        public string $rawLine,
    ) {}
}
