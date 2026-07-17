<?php

declare(strict_types=1);

/**
 * Thrown when a session has expired due to inactivity or absolute timeout.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Session\Exception;

final class SessionExpiredException extends SessionException
{
    /**
     * Raised when a session has passed its configured lifetime.
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
     * Get the expired session ID.
     *
     * @return string
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }
}
