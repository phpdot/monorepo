<?php

declare(strict_types=1);

/**
 * ChaCha20-Poly1305 Encryptor
 *
 * Authenticated encryption using ChaCha20-Poly1305: encrypt and base64-encode for safe
 * transport and storage. No pre-encryption compression (avoids CRIME/BREACH-class length leaks).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Encryption;

use PHPdot\Contracts\Logs\EncryptorInterface;
use PHPdot\TraceLog\Exception\EncryptionException;

final class ChaChaEncryptor implements EncryptorInterface
{
    private const CIPHER    = 'chacha20-poly1305';
    private const NONCE_LEN = 12;
    private const TAG_LEN   = 16;
    private const KEY_LEN   = 32;

    private readonly string $key;

    /**
     * Create a new ChaChaEncryptor instance.
     *
     * @param string $key Base64-encoded 256-bit (32-byte) encryption key
     *
     * @throws EncryptionException If the key is invalid
     */
    public function __construct(string $key)
    {
        $decoded = base64_decode($key, true);

        if ($decoded === false || strlen($decoded) !== self::KEY_LEN) {
            throw EncryptionException::invalidKey();
        }

        $this->key = $decoded;
    }

    /**
     * Generate a new random encryption key.
     *
     * @return string Base64-encoded 256-bit key
     */
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(self::KEY_LEN));
    }

    /**
     * Encrypt a plaintext string (encrypt, base64-encode).
     *
     * @param string $plaintext The plaintext to encrypt
     *
     * @throws EncryptionException If encryption fails
     *
     * @return string Base64-encoded ciphertext
     */
    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(self::NONCE_LEN);
        $tag   = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LEN,
        );

        if ($ciphertext === false) {
            throw EncryptionException::encryptionFailed();
        }

        return base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * Decrypt a ciphertext string (base64-decode, decrypt).
     *
     * @param string $ciphertext Base64-encoded ciphertext
     *
     * @throws EncryptionException If decoding or decryption fails
     *
     * @return string The decrypted plaintext
     */
    public function decrypt(string $ciphertext): string
    {
        $payload = base64_decode($ciphertext, true);

        $minLength = self::NONCE_LEN + self::TAG_LEN;

        if ($payload === false || strlen($payload) < $minLength) {
            throw EncryptionException::invalidPayload();
        }

        $nonce      = substr($payload, 0, self::NONCE_LEN);
        $tag        = substr($payload, self::NONCE_LEN, self::TAG_LEN);
        $encrypted  = substr($payload, $minLength);

        $message = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );

        if ($message === false) {
            throw EncryptionException::decryptionFailed();
        }

        return $message;
    }
}
