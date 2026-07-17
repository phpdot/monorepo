<?php

declare(strict_types=1);

/**
 * Thrown when session data cannot be read from the storage backend.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Session\Exception;

final class SessionReadException extends SessionException
{
    /**
     * Raised when the storage handler fails to read a session.
     *
     * @param string $message
     * @param string $sessionId
     * @param int $code
     * @param ?\Throwable $previous
     */
    public function __construct(
        string $message,
        private readonly string $sessionId,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the session ID that failed to read.
     *
     * @return string
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }
}
