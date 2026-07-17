<?php

declare(strict_types=1);

/**
 * Thrown when a template contains a syntax error.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Template\Exception;

use Throwable;

final class TemplateSyntaxException extends TemplateException
{
    /**
     * Raised when a template has a Twig syntax error.
     *
     * @param string $template
     * @param int $templateLine
     * @param string $message
     * @param ?Throwable $previous
     */
    public function __construct(
        public readonly string $template,
        public readonly int $templateLine,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
