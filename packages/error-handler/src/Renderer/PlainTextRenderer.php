<?php

declare(strict_types=1);

/**
 * Plain text error output for CLI environments.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Renderer;

use PHPdot\ErrorHandler\Context\ErrorContext;
use PHPdot\ErrorHandler\Contract\RendererInterface;

final class PlainTextRenderer implements RendererInterface
{
    public function render(ErrorContext $context): string
    {
        $e = $context->exception;

        $output = sprintf(
            "[%s] %s in %s:%d\n",
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
        );

        if ($context->isDevelopment) {
            $output .= "\nStack trace:\n";
            foreach ($context->stackTrace->frames as $i => $frame) {
                $call = ($frame->class ?? '') . ($frame->class !== null ? '::' : '') . ($frame->function ?? '{main}');
                $output .= sprintf(
                    "#%d %s:%d %s()\n",
                    $i,
                    $frame->file,
                    $frame->line,
                    $call,
                );
            }

            if ($context->solutions !== []) {
                $output .= "\nSuggested solutions:\n";
                foreach ($context->solutions as $solution) {
                    $output .= sprintf("  - %s: %s\n", $solution->title, $solution->description);
                }
            }
        }

        return $output;
    }
}
