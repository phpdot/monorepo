<?php

declare(strict_types=1);

namespace PHPdot\Template\Tests\Unit;

use PHPdot\Template\Exception\TemplateException;
use PHPdot\Template\Exception\TemplateNotFoundException;
use PHPdot\Template\Exception\TemplateRenderException;
use PHPdot\Template\Exception\TemplateSyntaxException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExceptionTest extends TestCase
{
    public function test_template_exception_extends_runtime_exception(): void
    {
        self::assertTrue(is_subclass_of(TemplateException::class, RuntimeException::class));
    }

    public function test_not_found_preserves_context(): void
    {
        $previous = new \Exception('loader');
        $e = new TemplateNotFoundException('missing.twig', 'could not find', $previous);

        self::assertSame('missing.twig', $e->template);
        self::assertSame('could not find', $e->getMessage());
        self::assertSame($previous, $e->getPrevious());
        self::assertInstanceOf(TemplateException::class, $e);
    }

    public function test_syntax_preserves_context(): void
    {
        $previous = new \Exception('syntax');
        $e = new TemplateSyntaxException('broken.twig', 42, 'unexpected token', $previous);

        self::assertSame('broken.twig', $e->template);
        self::assertSame(42, $e->templateLine);
        self::assertSame('unexpected token', $e->getMessage());
        self::assertSame($previous, $e->getPrevious());
    }

    public function test_render_preserves_context(): void
    {
        $previous = new \Exception('runtime');
        $e = new TemplateRenderException('page.twig', 7, 'undefined variable', $previous);

        self::assertSame('page.twig', $e->template);
        self::assertSame(7, $e->templateLine);
        self::assertSame('undefined variable', $e->getMessage());
        self::assertSame($previous, $e->getPrevious());
    }
}
