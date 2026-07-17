<?php

declare(strict_types=1);

namespace PHPdot\Template\Tests\Unit;

use PHPdot\Package\Manifest;
use PHPdot\Template\EngineFactory;
use PHPdot\Template\Exception\TemplateNotFoundException;
use PHPdot\Template\Exception\TemplateRenderException;
use PHPdot\Template\Exception\TemplateSyntaxException;
use PHPdot\Template\TemplateConfig;
use PHPdot\Template\Tests\Stubs\ArrayContainer;
use PHPdot\Template\View;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

final class ViewTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        $config = new TemplateConfig(paths: ['__main__' => [__DIR__ . '/../fixtures/views']]);
        $manifest = new Manifest([], '2026-04-22T00:00:00+00:00');
        $factory = new EngineFactory($config, $manifest, new ArrayContainer());

        $this->view = new View($factory);
    }

    public function test_render_returns_rendered_template(): void
    {
        $output = $this->view->render('hello.twig', ['name' => 'Omar']);

        self::assertSame("Hello, Omar!\n", $output);
    }

    public function test_render_block_returns_block_output(): void
    {
        $output = $this->view->renderBlock('blocks.twig', 'title', ['name' => 'Omar']);

        self::assertSame('Title: Omar', $output);
    }

    public function test_exists_returns_true_for_known_template(): void
    {
        self::assertTrue($this->view->exists('hello.twig'));
    }

    public function test_exists_returns_false_for_unknown_template(): void
    {
        self::assertFalse($this->view->exists('missing.twig'));
    }

    public function test_environment_returns_underlying_instance(): void
    {
        self::assertInstanceOf(Environment::class, $this->view->environment());
    }

    public function test_missing_template_wrapped(): void
    {
        try {
            $this->view->render('does_not_exist.twig');
            self::fail('Expected TemplateNotFoundException.');
        } catch (TemplateNotFoundException $e) {
            self::assertSame('does_not_exist.twig', $e->template);
            self::assertNotNull($e->getPrevious());
        }
    }

    public function test_missing_template_for_render_block_wrapped(): void
    {
        try {
            $this->view->renderBlock('does_not_exist.twig', 'title');
            self::fail('Expected TemplateNotFoundException.');
        } catch (TemplateNotFoundException $e) {
            self::assertSame('does_not_exist.twig', $e->template);
        }
    }

    public function test_syntax_error_wrapped(): void
    {
        try {
            $this->view->render('syntax_error.twig');
            self::fail('Expected TemplateSyntaxException.');
        } catch (TemplateSyntaxException $e) {
            self::assertSame('syntax_error.twig', $e->template);
            self::assertGreaterThan(0, $e->templateLine);
            self::assertNotNull($e->getPrevious());
        }
    }

    public function test_runtime_error_wrapped(): void
    {
        try {
            $this->view->render('runtime_error.twig');
            self::fail('Expected TemplateRenderException.');
        } catch (TemplateRenderException $e) {
            self::assertSame('runtime_error.twig', $e->template);
            self::assertGreaterThan(0, $e->templateLine);
            self::assertNotNull($e->getPrevious());
        }
    }
}
