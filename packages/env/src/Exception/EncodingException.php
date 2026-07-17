<?php

declare(strict_types=1);

/**
 * EncodingException
 *
 * Thrown when an environment file contains invalid encoding.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env\Exception;

final class EncodingException extends EnvException
{
    /**
     * Create the exception with an encoding failure message.
     *
     * @param string $message A description of the encoding error.
     */
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
