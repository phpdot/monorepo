<?php

declare(strict_types=1);

/**
 * JSON-based session serializer. Safe default — no object injection risk.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Session\Serializer;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Contracts\Session\SerializerInterface;
use PHPdot\Session\Exception\SessionException;

#[Singleton]
#[Binds(SerializerInterface::class)]
final class JsonSerializer implements SerializerInterface
{
    /**
     * {@inheritDoc}
     */
    public function encode(array $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new SessionException('Failed to encode session data: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function decode(string $data): array
    {
        if ($data === '') {
            return [];
        }

        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SessionException('Failed to decode session data: ' . $e->getMessage(), previous: $e);
        }

        if (!\is_array($decoded)) {
            return [];
        }

        /**
         * @var array<string, mixed>
         */
        return $decoded;
    }
}
