<?php

declare(strict_types=1);

/**
 * Wraps another renderer and returns a base64 `data:` URI instead of raw bytes.
 *
 * The URI can be dropped straight into an `<img src>` or a CSS `background-image`,
 * embedding the QR code inline with no extra HTTP request. The MIME type is
 * taken from the wrapped renderer's {@see ImageFormat}.
 *
 * Stateless — registered as a singleton.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode\Renderer;

use PHPdot\Container\Attribute\Singleton;
use PHPdot\QrCode\Contract\RendererInterface;
use PHPdot\QrCode\Matrix;
use PHPdot\QrCode\RenderOptions;

#[Singleton]
final class DataUriRenderer
{
    /**
     * Render `$matrix` with `$renderer` and wrap the result as a `data:` URI.
     *
     * @param RendererInterface $renderer
     * @param Matrix $matrix
     * @param RenderOptions $options
     *
     * @return string
     */
    public function render(RendererInterface $renderer, Matrix $matrix, RenderOptions $options): string
    {
        $bytes = $renderer->render($matrix, $options);

        return sprintf(
            'data:%s;base64,%s',
            $renderer->format()->mimeType(),
            base64_encode($bytes),
        );
    }
}
