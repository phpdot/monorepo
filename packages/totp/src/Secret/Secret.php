<?php

declare(strict_types=1);

/**
 * An immutable OTP shared secret.
 *
 * Holds the raw bytes used for HMAC and converts to/from the Base32 form shared
 * with authenticator apps. New secrets come from `generate()`, which uses the
 * CSPRNG (`random_bytes`) and enforces a 128-bit minimum.
 *
 * The secret is a symmetric credential: anyone holding it can generate valid
 * codes forever. It CANNOT be hashed at rest (verification needs the original),
 * so it must be encrypted before storage — see the package README. Constructor
 * and factory inputs are marked `#[SensitiveParameter]` to keep the raw value
 * out of stack traces.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp\Secret;

use PHPdot\Totp\Exception\InvalidParameterException;
use PHPdot\Totp\Exception\InvalidSecretException;
use SensitiveParameter;

final readonly class Secret
{
    /**
     * Wraps the raw secret bytes, rejecting an empty value.
     *
     * @param string $bytes
     */
    public function __construct(
        #[SensitiveParameter]
        private string $bytes,
    ) {
        if ($bytes === '') {
            throw new InvalidSecretException('Secret must not be empty.');
        }
    }

    /**
     * Generate a fresh random secret using the CSPRNG.
     *
     * @param int $length Number of random bytes; must be at least 16 (128-bit).
     *
     * @throws InvalidParameterException if `$length` is below 16
     *
     * @return self
     */
    public static function generate(int $length = 20): self
    {
        if ($length < 16) {
            throw new InvalidParameterException(
                "Secret length must be at least 16 bytes (128-bit), got {$length}.",
            );
        }

        return new self(random_bytes($length));
    }

    /**
     * Build a secret from its Base32 representation.
     *
     * @param string $base32
     *
     * @return Secret
     */
    public static function fromBase32(#[SensitiveParameter] string $base32): self
    {
        return new self(Base32::decode($base32));
    }

    /**
     * The Base32 form to embed in a provisioning URI or show to the user.
     *
     * @return string
     */
    public function toBase32(): string
    {
        return Base32::encode($this->bytes);
    }

    /**
     * The raw secret bytes (for HMAC computation).
     *
     * @return string
     */
    public function bytes(): string
    {
        return $this->bytes;
    }
}
