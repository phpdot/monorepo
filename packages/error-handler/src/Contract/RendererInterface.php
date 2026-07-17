<?php

declare(strict_types=1);

/**
 * Renders an ErrorContext into a string (HTML, JSON, plain text, etc.).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Contract;

use PHPdot\ErrorHandler\Context\ErrorContext;

interface RendererInterface
{
    /**
     * Render the error context into a string.
     *
     * @param ErrorContext $context
     *
     * @return string
     */
    public function render(ErrorContext $context): string;
}
