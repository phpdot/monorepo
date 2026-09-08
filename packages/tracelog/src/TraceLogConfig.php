<?php

declare(strict_types=1);

/**
 * TraceLog configuration, hydrated from `config/tracelog.php` by phpdot/package
 * when the `#[Config]` attribute is scanned. Works standalone via its defaults.
 *
 * This is the only way runtime values reach the writer: the generated container
 * definitions construct {@see TraceLogWriter} from this DTO, so an install is
 * configured entirely by the scaffolded config file — no binding closures.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog;

use PHPdot\Container\Attribute\Config;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\TraceLog\Exception\TraceLogException;
use SensitiveParameter;

#[Singleton]
#[Config('tracelog')]
final readonly class TraceLogConfig
{
    /**
     * The configured encryption key, or null when encryption is disabled.
     *
     * An empty string — the shape a blank `TRACELOG_KEY=` line yields — is
     * normalized to null here, so "present but empty" means disabled rather
     * than a boot failure. Only a non-empty malformed key fails construction.
     */
    public readonly null|string $encryptionKey;

    /**
     * Creates the TraceLog configuration, rejecting shapes that would fail
     * silently at runtime (an empty path, an unknown formatter name).
     *
     * @param string $basePath Base directory for the per-channel log files;
     *                         each channel materializes as `{basePath}/{channel}.log`
     *                         on first use. The directory is created when missing.
     * @param int $minLevel Minimum severity to write, as a PSR-3/Monolog integer
     *                      (DEBUG 100 … EMERGENCY 600). Records below it are dropped.
     * @param string $defaultFormatter Line format for every channel: 'json' (one JSON
     *                                 object per line, machine-readable) or 'text'
     *                                 (human-readable development format).
     * @param int $maxChannels Maximum number of cached channel handlers; the least
     *                         recently used channel is evicted beyond it.
     * @param string|null $encryptionKey Base64-encoded 256-bit key for the fail-closed
     *                                   encryption of `->secure()` records; null (or an
     *                                   empty string) disables encryption — secure
     *                                   records are then dropped. Read from `TRACELOG_KEY`.
     * @param bool $enabled Master switch: false discards every record at the writer
     *                      (the binding stays, tracing stays on, output goes off).
     */
    public function __construct(
        public string $basePath = '/var/log/app',
        public int $minLevel = 100,
        public string $defaultFormatter = 'json',
        public int $maxChannels = 50,
        #[SensitiveParameter]
        null|string $encryptionKey = null,
        public bool $enabled = true,
    ) {
        if ($basePath === '') {
            throw new TraceLogException('TraceLog base path must not be empty.');
        }

        if (!in_array($defaultFormatter, ['json', 'text'], true)) {
            throw new TraceLogException(
                "TraceLog formatter must be 'json' or 'text', got '{$defaultFormatter}'.",
            );
        }

        if ($minLevel < 100 || $minLevel > 600) {
            throw new TraceLogException(
                "TraceLog minimum level must be a PSR-3 integer between 100 and 600, got {$minLevel}.",
            );
        }

        if ($maxChannels < 1) {
            throw new TraceLogException(
                "TraceLog maximum channel count must be at least 1, got {$maxChannels}.",
            );
        }

        $this->encryptionKey = $encryptionKey === '' ? null : $encryptionKey;
    }
}
