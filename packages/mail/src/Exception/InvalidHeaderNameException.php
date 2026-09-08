<?php

declare(strict_types=1);

/**
 * Thrown when a custom header name cannot be a header at all: a name must be
 * an RFC 5322 field name — printable ASCII without a colon — so a line break
 * in a name is rejected where it is set instead of injected onto the wire.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Exception;

use Throwable;

final class InvalidHeaderNameException extends MailException
{
    /**
     * Rejects a name that is not an RFC 5322 field name.
     *
     * @param string $name
     *
     * @return self
     */
    public static function notAFieldName(string $name): self
    {
        return new self(
            sprintf('Header name %s is not an RFC 5322 field name.', self::rendered($name)),
            $name,
        );
    }

    /**
     * Creates the exception over a message and the offending name.
     *
     * @param string $message
     * @param string $headerName
     * @param int $code
     * @param null|Throwable $previous
     */
    public function __construct(
        string $message = '',
        private readonly string $headerName = '',
        int $code = 0,
        null|Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * The rejected name, verbatim — context a trace log can lift next to the
     * exception shape, where the writer escapes control characters itself.
     *
     * @return string
     */
    public function getHeaderName(): string
    {
        return $this->headerName;
    }

    /**
     * Renders the offending name log-safe: JSON-escaped when encodable — a
     * line break comes out escaped, keeping the message one line for the
     * writers that interpolate it raw — or hex when the bytes are not valid
     * UTF-8 at all.
     *
     * @param string $name
     *
     * @return string
     */
    private static function rendered(string $name): string
    {
        $encoded = json_encode($name);

        return $encoded === false ? '"' . bin2hex($name) . '"' : $encoded;
    }
}
