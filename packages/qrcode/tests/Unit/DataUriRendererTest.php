<?php

declare(strict_types=1);

namespace PHPdot\QrCode\Tests\Unit;

use PHPdot\QrCode\Matrix;
use PHPdot\QrCode\Renderer\DataUriRenderer;
use PHPdot\QrCode\Renderer\SvgRenderer;
use PHPdot\QrCode\RenderOptions;
use PHPUnit\Framework\TestCase;

final class DataUriRendererTest extends TestCase
{
    public function test_wraps_renderer_output_as_base64_uri(): void
    {
        $matrix = new Matrix([[true]], 1);
        $options = new RenderOptions(size: 30, margin: 1);
        $svgRenderer = new SvgRenderer();

        $uri = (new DataUriRenderer())->render($svgRenderer, $matrix, $options);

        self::assertStringStartsWith('data:image/svg+xml;base64,', $uri);

        $payload = substr($uri, strlen('data:image/svg+xml;base64,'));
        $decoded = base64_decode($payload, true);

        self::assertSame($svgRenderer->render($matrix, $options), $decoded);
    }
}
