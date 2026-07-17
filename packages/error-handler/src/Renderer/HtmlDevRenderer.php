<?php

declare(strict_types=1);

/**
 * Beautiful development debug page renderer.
 *
 * Uses a PHP template file that receives $errorContext.
 * Replace the template path to fully customize the dev page.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Renderer;

use PHPdot\ErrorHandler\Context\ErrorContext;
use PHPdot\ErrorHandler\Contract\RendererInterface;

final class HtmlDevRenderer implements RendererInterface
{
    /**
     * Bind the renderer to its dev-page template.
     *
     * @param string $templatePath Path to the PHP template that receives $errorContext
     */
    public function __construct(
        private readonly string $templatePath = __DIR__ . '/../../templates/dev.html.php',
    ) {}

    public function render(ErrorContext $context): string
    {
        $errorContext = $context;

        ob_start();
        require $this->templatePath;

        return ob_get_clean();
    }
}
