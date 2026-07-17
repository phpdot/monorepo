<?php

declare(strict_types=1);

/**
 * Consumer-facing template view.
 *
 * Wraps `Twig\Environment` with framework-native exceptions and a minimal
 * surface. For advanced use (runtime-only APIs, custom loaders, direct
 * access) call `environment()`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Template;

use PHPdot\Container\Attribute\Singleton;
use PHPdot\Template\Exception\TemplateNotFoundException;
use PHPdot\Template\Exception\TemplateRenderException;
use PHPdot\Template\Exception\TemplateSyntaxException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Singleton]
final readonly class View
{
    /**
     * Wrap the engine factory to render named templates to strings.
     *
     * @param EngineFactory $factory Provides the shared Twig Environment
     */
    public function __construct(
        private EngineFactory $factory,
    ) {}

    /**
     * Render a template to a string.
     *
     * @param array<string, mixed> $context
     * @param string $template
     *
     * @throws TemplateNotFoundException If the template cannot be located.
     * @throws TemplateSyntaxException If the template contains a syntax error.
     * @throws TemplateRenderException If rendering fails at runtime.
     *
     * @return string
     */
    public function render(string $template, array $context = []): string
    {
        try {
            return $this->factory->environment()->render($template, $context);
        } catch (LoaderError $e) {
            throw new TemplateNotFoundException($template, $e->getMessage(), $e);
        } catch (SyntaxError $e) {
            throw new TemplateSyntaxException($template, $e->getTemplateLine(), $e->getMessage(), $e);
        } catch (RuntimeError $e) {
            throw new TemplateRenderException($template, $e->getTemplateLine(), $e->getMessage(), $e);
        }
    }

    /**
     * Render a single block of a template to a string.
     *
     * @param array<string, mixed> $context
     * @param string $block
     * @param string $template
     *
     * @throws TemplateNotFoundException If the template cannot be located.
     * @throws TemplateSyntaxException If the template contains a syntax error.
     * @throws TemplateRenderException If rendering fails at runtime.
     *
     * @return string
     */
    public function renderBlock(string $template, string $block, array $context = []): string
    {
        try {
            $loaded = $this->factory->environment()->load($template);

            return $loaded->renderBlock($block, $context);
        } catch (LoaderError $e) {
            throw new TemplateNotFoundException($template, $e->getMessage(), $e);
        } catch (SyntaxError $e) {
            throw new TemplateSyntaxException($template, $e->getTemplateLine(), $e->getMessage(), $e);
        } catch (RuntimeError $e) {
            throw new TemplateRenderException($template, $e->getTemplateLine(), $e->getMessage(), $e);
        }
    }

    /**
     * Check whether a template exists in any registered loader.
     *
     * @param string $template
     *
     * @return bool
     */
    public function exists(string $template): bool
    {
        return $this->factory->environment()->getLoader()->exists($template);
    }

    /**
     * Escape hatch to the underlying Twig environment.
     *
     * @return Environment
     */
    public function environment(): Environment
    {
        return $this->factory->environment();
    }
}
