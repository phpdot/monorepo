<?php

declare(strict_types=1);

/**
 * ProjectRoot
 *
 * The project root: the directory containing the application's composer.json.
 * This is the only place root resolution lives — no __DIR__ elsewhere. The root
 * is taken from Composer's runtime metadata, which is authoritative regardless of
 * the working directory or a relocated vendor-dir (the runtime twin of the
 * install hook's Factory::getComposerFile()).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Path;

use Composer\InstalledVersions;
use PHPdot\Path\Exception\ProjectRootNotFound;

final readonly class ProjectRoot
{
    /**
     * Hold a discovered, absolute project-root path.
     *
     * @param string $path Absolute path to the project root
     */
    private function __construct(
        public string $path,
    ) {}

    /**
     * Resolve the project root from Composer's runtime metadata.
     *
     * @throws ProjectRootNotFound When the resolved path is not a directory
     *
     * @return self
     */
    public static function discover(): self
    {
        $installPath = InstalledVersions::getRootPackage()['install_path'];
        $resolved = realpath($installPath);

        if ($resolved === false || !is_dir($resolved)) {
            throw ProjectRootNotFound::unresolvable($installPath);
        }

        return new self($resolved);
    }

    /**
     * Build from an explicit, known-good path (e.g. for tests or odd setups).
     *
     * @param string $path
     *
     * @throws ProjectRootNotFound When the path is not a directory
     *
     * @return ProjectRoot
     */
    public static function fromPath(string $path): self
    {
        $resolved = realpath($path);

        if ($resolved === false || !is_dir($resolved)) {
            throw ProjectRootNotFound::invalidPath($path);
        }

        return new self($resolved);
    }
}
