<?php

declare(strict_types=1);

/**
 * Encryptor contract — symmetric encryption for sensitive log records.
 *
 * Backends honoring `->secure()` encrypt a record's message and context before
 * export; `decrypt()` reverses `encrypt()`. A backend with no encryptor MUST
 * fail closed — drop the record, never write plaintext.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Logs;

interface EncryptorInterface
{
    /**
     * Encrypt a plaintext string.
     *
     * @param string $plaintext The plaintext to encrypt.
     *
     * @return string The encrypted ciphertext.
     */
    public function encrypt(string $plaintext): string;

    /**
     * Decrypt a ciphertext string.
     *
     * @param string $ciphertext The ciphertext to decrypt.
     *
     * @return string The decrypted plaintext.
     */
    public function decrypt(string $ciphertext): string;
}
