<?php

declare(strict_types=1);

/**
 * Discovers console commands by scanning for the command attribute.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Console\Discovery;

use PHPdot\Attribute\Scanner;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

final class CommandDiscovery
{
    /**
     * __construct.
     *
     * @param Scanner $scanner
     */
    public function __construct(
        private readonly Scanner $scanner = new Scanner(),
    ) {}

    /**
     * Discover command classes with #[AsCommand] in the given directories.
     *
     * @param list<string> $directories Directories to scan for command classes
     *
     * @return array<string, class-string<SymfonyCommand>> Command name to class map
     */
    public function discover(array $directories): array
    {
        $existing = array_values(array_filter($directories, is_dir(...)));

        if ($existing === []) {
            return [];
        }

        $registry = $this->scanner->scan(
            directories: $existing,
            filter: [AsCommand::class],
            forceRescan: true,
        );

        $commandMap = [];

        foreach ($registry->findByAttribute(AsCommand::class) as $result) {
            $class = $result->class;

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            if (!$reflection->isSubclassOf(SymfonyCommand::class)) {
                continue;
            }

            $attribute = $result->instance;

            if (!$attribute instanceof AsCommand) {
                continue;
            }

            $name = $attribute->name;

            if ($name === '') {
                continue;
            }

            /**
             * @var class-string<SymfonyCommand> $class
             */
            $commandMap[$name] = $class;
        }

        ksort($commandMap);

        return $commandMap;
    }
}
