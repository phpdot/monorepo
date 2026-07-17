<?php

declare(strict_types=1);

/**
 * Thrown when a template cannot be resolved by any loader.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Template\Exception;

use Throwable;

final class TemplateNotFoundException extends TemplateException
{
    /**
     * Raised when a named template cannot be found.
     *
     * @param string $template
     * @param string $message
     * @param ?Throwable $previous
     */
    public function __construct(
        public readonly string $template,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
