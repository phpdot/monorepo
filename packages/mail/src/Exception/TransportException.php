<?php

declare(strict_types=1);

/**
 * Thrown when the underlying transport fails to deliver a message (connection
 * refused, authentication rejected, recipient declined, etc.). Carries the
 * transport's scheme — never the DSN, which holds credentials — so a catching
 * layer can log which wire failed without learning any secret.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Exception;

use Throwable;

final class TransportException extends MailException
{
    /**
     * Creates the exception over a message and the transport scheme it failed on.
     *
     * @param string $message
     * @param string $scheme
     * @param int $code
     * @param null|Throwable $previous
     */
    public function __construct(
        string $message = '',
        private readonly string $scheme = '',
        int $code = 0,
        null|Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * The transport family that failed, e.g. "smtp" — never the full DSN.
     *
     * @return string
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }
}
