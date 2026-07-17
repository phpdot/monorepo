<?php

declare(strict_types=1);

/**
 * PHP serialize/unserialize codec for session payloads.
 *
 * Uses `allowed_classes: false` on decode so untrusted payloads cannot
 * instantiate arbitrary classes during deserialisation — the actual
 * mechanism that prevents PHP Object Injection (POI). Any objects present
 * in the payload come back as `__PHP_Incomplete_Class` placeholders rather
 * than functioning instances: no constructors run, no autoloading triggers,
 * no magic methods fire.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Session\Serializer;

use PHPdot\Container\Attribute\Singleton;
use PHPdot\Contracts\Session\SerializerInterface;
use PHPdot\Session\Exception\SessionException;

#[Singleton]
final class PhpSerializer implements SerializerInterface
{
    /**
     * {@inheritDoc}
     */
    public function encode(array $data): string
    {
        return serialize($data);
    }

    /**
     * {@inheritDoc}
     */
    public function decode(string $data): array
    {
        if ($data === '') {
            return [];
        }

        set_error_handler(static function (int $severity, string $message): never {
            throw new SessionException('Failed to decode session data: ' . $message);
        });

        try {
            $result = unserialize($data, ['allowed_classes' => false]);
        } finally {
            restore_error_handler();
        }

        if (!\is_array($result)) {
            throw new SessionException('Failed to decode session data: unserialize returned non-array.');
        }

        /**
         * @var array<string, mixed>
         */
        return $result;
    }
}
