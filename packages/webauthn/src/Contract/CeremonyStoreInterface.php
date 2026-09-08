<?php

declare(strict_types=1);

/**
 * Per-ceremony state: the options JSON keyed by the challenge, readable
 * exactly once. Single-use is replay protection — the library only compares
 * challenges, so consuming them on read is the package's law — and the TTL
 * bounds how long a begun ceremony may hang unfinished.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn\Contract;

interface CeremonyStoreInterface
{
    /**
     * Park the ceremony state under its challenge.
     *
     * @param string $challenge The challenge (base64url)
     * @param string $stateJson The serialized options
     *
     * @return void
     */
    public function save(string $challenge, string $stateJson): void;

    /**
     * Take the ceremony state back — once. A second read of the same
     * challenge answers null: consumed, expired, or never issued.
     *
     * @param string $challenge The challenge (base64url)
     *
     * @return string|null
     */
    public function consume(string $challenge): null|string;
}
