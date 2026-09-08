<?php

declare(strict_types=1);

/**
 * Hashes and verifies passwords, and reports when a stored hash no longer
 * matches the current algorithm/parameters — the seam behind rehash-on-login
 * (P3 ruling): verify succeeds, needsRehash() answers true, the still-present
 * plaintext is re-hashed and the row updated. That is the entire migration
 * story for parameter upgrades AND algorithm changes — password_verify()
 * reads the algorithm from the hash prefix, so legacy hashes keep verifying
 * until they self-upgrade. No mass resets, ever.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication\Contract;

interface PasswordHasherInterface
{
    /**
     * Hash a plaintext password.
     *
     * @param string $plain The plaintext
     *
     * @return string
     */
    public function hash(string $plain): string;

    /**
     * Whether the plaintext matches the stored hash.
     *
     * @param string $plain The plaintext
     * @param string $hash The stored hash
     *
     * @return bool
     */
    public function verify(string $plain, string $hash): bool;

    /**
     * Whether the stored hash predates the current algorithm/parameters.
     *
     * @param string $hash The stored hash
     *
     * @return bool
     */
    public function needsRehash(string $hash): bool;
}
