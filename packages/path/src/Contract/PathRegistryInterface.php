<?php

declare(strict_types=1);

/**
 * PathRegistryInterface
 *
 * Named path resolution for the application. Consumers depend on this contract,
 * not the concrete registry.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Path\Contract;

interface PathRegistryInterface
{
    /**
     * Absolute project root.
     *
     * @return string
     */
    public function base(): string;

    /**
     * Application configuration directory.
     *
     * @return string
     */
    public function config(): string;

    /**
     * Composer vendor directory.
     *
     * @return string
     */
    public function vendor(): string;

    /**
     * Web server document root.
     *
     * @return string
     */
    public function public(): string;

    /**
     * Protected storage outside the web root.
     *
     * @return string
     */
    public function protected(): string;

    /**
     * Resolve any mapped name (built-in or custom) to its absolute path.
     *
     * @param string $name
     *
     * @return string
     */
    public function get(string $name): string;

    /**
     * Whether a path name is mapped.
     *
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool;
}
