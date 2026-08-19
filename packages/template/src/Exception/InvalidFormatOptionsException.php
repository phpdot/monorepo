<?php

declare(strict_types=1);

/**
 * Thrown when `template.formatOptions` contains an option tidy rejects.
 *
 * Tidy raises a ValueError for an unknown option name and a TypeError for a
 * value it will not accept — per call. Left alone, a single typo in config
 * would therefore blow up on every page render for the life of the release.
 * The formatter probes the options once at construction instead, so a bad
 * option is a boot-time error naming the option, not a runtime surprise.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Template\Exception;

use Throwable;

final class InvalidFormatOptionsException extends TemplateException
{
    /**
     * Raised when tidy rejects the configured options.
     *
     * @param string $reason Tidy's own complaint, naming the option at fault
     * @param ?Throwable $previous
     */
    public function __construct(string $reason, null|Throwable $previous = null)
    {
        parent::__construct(
            'template.formatOptions were rejected by tidy: ' . $reason
            . ' Correct the option in your template config, or remove it to fall back to the defaults.',
            0,
            $previous,
        );
    }
}
