<?php

declare(strict_types=1);

/**
 * Immutable session configuration.
 *
 * Constructor arguments are validated; bad input fails fast at construction
 * time rather than producing malformed cookies or surprising runtime behaviour.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Session;

use InvalidArgumentException;
use PHPdot\Container\Attribute\Config;
use PHPdot\Container\Attribute\Singleton;

#[Singleton]
#[Config('session')]
final readonly class SessionConfig
{
    public const string SAME_SITE_STRICT = 'Strict';
    public const string SAME_SITE_LAX    = 'Lax';
    public const string SAME_SITE_NONE   = 'None';

    /**
     * Immutable session cookie and lifetime settings.
     *
     * @param string $name
     * @param int $lifetime
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httpOnly
     * @param string $sameSite
     * @param bool $partitioned
     * @param int $gcProbability
     * @param string $savePath
     */
    public function __construct(
        public string $name = 'phpdot_session',
        public int $lifetime = 7200,
        public string $path = '/',
        public string $domain = '',
        public bool $secure = true,
        public bool $httpOnly = true,
        public string $sameSite = self::SAME_SITE_LAX,
        public bool $partitioned = false,
        public int $gcProbability = 2,
        public string $savePath = '/tmp/phpdot_sessions',
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('SessionConfig: name must not be empty.');
        }

        if (preg_match('/[\s=;,\x00-\x1F\x7F]/', $name) === 1) {
            throw new InvalidArgumentException(\sprintf(
                'SessionConfig: name "%s" contains invalid characters (whitespace, control chars, or any of = ; ,).',
                $name,
            ));
        }

        if ($lifetime < 0) {
            throw new InvalidArgumentException(\sprintf(
                'SessionConfig: lifetime must be >= 0, got %d.',
                $lifetime,
            ));
        }

        $validSameSite = [self::SAME_SITE_STRICT, self::SAME_SITE_LAX, self::SAME_SITE_NONE];
        if (!\in_array($sameSite, $validSameSite, true)) {
            throw new InvalidArgumentException(\sprintf(
                'SessionConfig: sameSite must be one of Strict, Lax, None — got "%s".',
                $sameSite,
            ));
        }

        if ($sameSite === self::SAME_SITE_NONE && !$secure) {
            throw new InvalidArgumentException(
                'SessionConfig: sameSite=None requires secure=true (modern browsers reject SameSite=None; Secure=false).',
            );
        }

        if ($gcProbability < 0 || $gcProbability > 100) {
            throw new InvalidArgumentException(\sprintf(
                'SessionConfig: gcProbability must be in [0, 100], got %d.',
                $gcProbability,
            ));
        }
    }
}
