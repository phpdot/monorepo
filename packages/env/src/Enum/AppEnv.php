<?php

declare(strict_types=1);

/**
 * AppEnv
 *
 * Represents the application environment (development, staging, production).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env\Enum;

enum AppEnv: string
{
    case DEVELOPMENT = 'development';
    case STAGING = 'staging';
    case PRODUCTION = 'production';

    /**
     * Checks whether this is the development environment.
     *
     * @return bool
     */
    public function isDevelopment(): bool
    {
        return $this === self::DEVELOPMENT;
    }

    /**
     * Checks whether this is the staging environment.
     *
     * @return bool
     */
    public function isStaging(): bool
    {
        return $this === self::STAGING;
    }

    /**
     * Checks whether this is the production environment.
     *
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this === self::PRODUCTION;
    }
}
