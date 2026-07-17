<?php

declare(strict_types=1);

/**
 * Lazy-style loader that holds pre-built command instances keyed by name.
 *
 * Used by Application when no DI container is provided — keeps the loader
 * as the single source of truth for which commands exist, so renames and
 * removals behave identically to the container-backed path.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Console;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

final class InstanceCommandLoader implements CommandLoaderInterface
{
    /**
     * Create the loader over a map of command instances.
     *
     * @param array<string, SymfonyCommand> $commands Command name to instance map
     */
    public function __construct(
        private readonly array $commands,
    ) {}

    public function get(string $name): SymfonyCommand
    {
        if (!isset($this->commands[$name])) {
            throw new CommandNotFoundException(sprintf('Command "%s" does not exist.', $name));
        }

        return $this->commands[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        return array_keys($this->commands);
    }
}
