<?php

declare(strict_types=1);

/**
 * Session serializer contract — bidirectional codec for session payloads.
 *
 * Converts session data between an associative array (in-memory form)
 * and a string (storage form). Implementations must round-trip the data,
 * must accept an empty string on decode, and may throw on malformed input.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Session;

interface SerializerInterface
{
    /**
     * Encode session data to a string.
     *
     * @param array<string, mixed> $data
     *
     * @return string
     */
    public function encode(array $data): string;

    /**
     * Decode a string back to session data.
     *
     * @param string $data
     *
     * @return array<string, mixed>
     */
    public function decode(string $data): array;
}
