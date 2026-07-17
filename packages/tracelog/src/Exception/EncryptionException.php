<?php

declare(strict_types=1);

/**
 * Encryption Exception
 *
 * Thrown when encryption or decryption operations fail.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Exception;

final class EncryptionException extends TraceLogException
{
    /**
     * Create for an invalid encryption key.
     *
     * @return self Exception instance
     */
    public static function invalidKey(): self
    {
        return new self('Encryption key must be a base64-encoded 256-bit (32 bytes) key');
    }

    /**
     * Create for an encryption failure.
     *
     * @return self Exception instance
     */
    public static function encryptionFailed(): self
    {
        return new self('Encryption failed');
    }

    /**
     * Create for an invalid encrypted payload.
     *
     * @return self Exception instance
     */
    public static function invalidPayload(): self
    {
        return new self('Invalid encrypted payload');
    }

    /**
     * Create for a decryption failure.
     *
     * @return self Exception instance
     */
    public static function decryptionFailed(): self
    {
        return new self('Decryption failed');
    }

    /**
     * Create when secure() is called but no encryptor is configured.
     *
     * @return self Exception instance
     */
    public static function secureWithoutEncryptor(): self
    {
        return new self('Cannot encrypt: secure() was called but no encryptor is configured');
    }

    /**
     * Create when the secure payload cannot be serialized for encryption.
     *
     * Carries no plaintext — the unencodable value must never leak into a message or trace.
     *
     * @return self Exception instance
     */
    public static function securePayloadNotEncodable(): self
    {
        return new self('Cannot encrypt: the log message or context could not be serialized');
    }
}
