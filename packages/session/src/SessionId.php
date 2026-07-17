<?php

declare(strict_types=1);

/**
 * Cryptographically secure session identifier.
 *
 * 32 bytes of randomness (256 bits) encoded as 64 lowercase hex characters.
 * Exceeds OWASP recommendation of 128 bits minimum entropy.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Session;

use PHPdot\Session\Exception\SessionException;

final class SessionId
{
    private const int BYTE_LENGTH = 32;

    private const int HEX_LENGTH = 64;

    private const string PATTERN = '/^[a-f0-9]{64}$/';

    /**
     * A validated, opaque session identifier.
     *
     * @param string $value
     */
    private function __construct(
        private readonly string $value,
    ) {}

    /**
     * Generate a new cryptographically random session ID.
     *
     * @return self
     */
    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(self::BYTE_LENGTH)));
    }

    /**
     * Create from an existing string, validating format.
     *
     * @param string $id
     *
     * @throws SessionException If the format is invalid.
     *
     * @return SessionId
     */
    public static function fromString(string $id): self
    {
        if (preg_match(self::PATTERN, $id) !== 1) {
            throw new SessionException(
                \sprintf('Invalid session ID format: expected %d lowercase hex characters.', self::HEX_LENGTH),
            );
        }

        return new self($id);
    }

    /**
     * Get the string representation.
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Constant-time comparison to prevent timing attacks.
     *
     * @param self $other
     *
     * @return bool
     */
    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
