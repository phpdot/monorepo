<?php

declare(strict_types=1);

/**
 * Builds and caches the `Twig\Environment` for the worker.
 *
 * The environment is constructed on first access and reused for the lifetime
 * of the worker. Extensions are discovered via the `phpdot/package` manifest:
 * any class implementing `Twig\Extension\ExtensionInterface` registered in the
 * container is added automatically.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Template;

use PHPdot\Container\Attribute\Singleton;
use PHPdot\Package\Manifest;
use Psr\Container\ContainerInterface;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\FilesystemLoader;

#[Singleton]
final class EngineFactory
{
    private ?Environment $environment = null;

    /**
     * Wire the factory to its config, package manifest, and container.
     *
     * @param TemplateConfig $config Template paths and rendering flags
     * @param Manifest $manifest Source of the packages whose Twig extensions are auto-discovered
     * @param ContainerInterface $container Resolves each discovered extension class to an instance
     */
    public function __construct(
        private readonly TemplateConfig $config,
        private readonly Manifest $manifest,
        private readonly ContainerInterface $container,
    ) {}

    /**
     * Return the shared `Twig\Environment`, building it on first call.
     *
     * @return Environment
     */
    public function environment(): Environment
    {
        return $this->environment ??= $this->build();
    }

    /**
     * Build the Twig Environment from config, loaders, and auto-discovered extensions.
     *
     * @return Environment
     */
    private function build(): Environment
    {
        $loader = new FilesystemLoader();

        foreach ($this->config->paths as $namespace => $paths) {
            foreach ($paths as $path) {
                $loader->addPath($path, $namespace);
            }
        }

        $environment = new Environment($loader, [
            'debug' => $this->config->debug,
            'charset' => $this->config->charset,
            'strict_variables' => $this->config->strictVariables,
            'autoescape' => $this->config->autoescape,
            'cache' => ($this->config->cache !== null && $this->config->cache !== '')
                ? $this->config->cache
                : false,
            'auto_reload' => $this->config->autoReload,
        ]);

        if ($this->config->debug) {
            $environment->addExtension(new DebugExtension());
        }

        foreach (array_keys($this->manifest->allServices()) as $class) {
            if (!is_subclass_of($class, ExtensionInterface::class)) {
                continue;
            }

            $extension = $this->container->get($class);

            if ($extension instanceof ExtensionInterface) {
                $environment->addExtension($extension);
            }
        }

        return $environment;
    }
}
