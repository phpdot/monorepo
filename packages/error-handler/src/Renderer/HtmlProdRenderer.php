<?php

declare(strict_types=1);

/**
 * Clean production error page renderer.
 *
 * Shows a user-friendly error message. No internals exposed.
 * Replace the template path to fully customize the production page.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Renderer;

use PHPdot\ErrorHandler\Context\ErrorContext;
use PHPdot\ErrorHandler\Contract\RendererInterface;

final class HtmlProdRenderer implements RendererInterface
{
    /**
     * Bind the renderer to its production-page template.
     *
     * @param string $templatePath Path to the PHP template that receives $errorContext
     */
    public function __construct(
        private readonly string $templatePath = __DIR__ . '/../../templates/prod.html.php',
    ) {}

    public function render(ErrorContext $context): string
    {
        $errorContext = $context;

        ob_start();
        require $this->templatePath;

        return ob_get_clean();
    }
}
