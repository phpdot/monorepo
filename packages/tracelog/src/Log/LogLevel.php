<?php

declare(strict_types=1);

/**
 * Log Level
 *
 * Integer constants for log severity levels following PSR-3/Monolog convention.
 * Provides level-to-name mapping for record building.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Log;

final class LogLevel
{
    public const int DEBUG     = 100;
    public const int INFO      = 200;
    public const int NOTICE    = 250;
    public const int WARNING   = 300;
    public const int ERROR     = 400;
    public const int CRITICAL  = 500;
    public const int ALERT     = 550;
    public const int EMERGENCY = 600;

    /**
     * Map of integer level to human-readable name.
     *
     * @var array<int, string>
     */
    private const array NAMES = [
        self::DEBUG     => 'DEBUG',
        self::INFO      => 'INFO',
        self::NOTICE    => 'NOTICE',
        self::WARNING   => 'WARNING',
        self::ERROR     => 'ERROR',
        self::CRITICAL  => 'CRITICAL',
        self::ALERT     => 'ALERT',
        self::EMERGENCY => 'EMERGENCY',
    ];

    /**
     * Map of PSR-3 string level to integer level.
     *
     * @var array<string, int>
     */
    private const array FROM_PSR = [
        'debug'     => self::DEBUG,
        'info'      => self::INFO,
        'notice'    => self::NOTICE,
        'warning'   => self::WARNING,
        'error'     => self::ERROR,
        'critical'  => self::CRITICAL,
        'alert'     => self::ALERT,
        'emergency' => self::EMERGENCY,
    ];

    /**
     * Get the human-readable name for a log level.
     *
     * @param int $level The integer log level
     *
     * @return string The level name, or 'UNKNOWN' for unrecognized levels
     */
    public static function name(int $level): string
    {
        return self::NAMES[$level] ?? 'UNKNOWN';
    }

    /**
     * Convert a PSR-3 string level to its integer equivalent.
     *
     * @param string $level The PSR-3 level string
     *
     * @return int The integer log level, defaults to DEBUG for unrecognized levels
     */
    public static function fromPsr(string $level): int
    {
        return self::FROM_PSR[strtolower($level)] ?? self::DEBUG;
    }
}
