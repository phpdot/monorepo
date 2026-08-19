<?php

declare(strict_types=1);

/**
 * Thrown when output formatting is switched on but ext-tidy is not installed.
 *
 * Raised once per worker, at construction — not per render — because it is a
 * configuration contradiction, not a runtime failure: the application asked
 * for formatted output and the machine cannot produce it. Failing here means
 * a misconfigured deploy is caught immediately, instead of silently serving
 * unformatted markup for the life of the release.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Template\Exception;

final class FormatterUnavailableException extends TemplateException
{
    /**
     * Raised when `template.format` is true on a machine without ext-tidy.
     */
    public function __construct()
    {
        parent::__construct(
            'HTML formatting is enabled (template.format = true) but the tidy extension is not '
            . 'loaded. Either install ext-tidy (PHP compiled --with-tidy, or the php-tidy package '
            . 'for your distribution) or set template.format to false.',
        );
    }
}
