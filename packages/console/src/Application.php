<?php

declare(strict_types=1);

/**
 * The PHPdot console application — attribute-discovered commands on Symfony Console.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Console;

use PHPdot\Console\Cache\CommandCache;
use PHPdot\Console\Discovery\CommandDiscovery;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class Application
{
    private readonly SymfonyApplication $symfony;

    private readonly ?CommandCache $cache;

    /**
     * @var array<string, class-string<SymfonyCommand>>
     */
    private array $commandMap = [];

    /**
     * @var array<string, array{name?: string, description?: string, help?: string}>
     */
    private array $modifications = [];

    /**
     * Create the application over its config, optionally with an explicit command cache.
     *
     * @param ContainerInterface|null $container PSR-11 container for resolving commands
     * @param CommandCache|null $cache Explicit cache; otherwise built from
     *                                 `$config->cachePath` when that is non-empty
     */
    public function __construct(
        ConsoleConfig $config = new ConsoleConfig(),
        private readonly ?ContainerInterface $container = null,
        ?CommandCache $cache = null,
    ) {
        $this->symfony = new SymfonyApplication($config->name, $config->version);
        $this->cache = $cache ?? ($config->cachePath !== '' ? new CommandCache($config->cachePath) : null);
    }

    /**
     * Discover commands in the given directories.
     *
     * @param list<string> $directories Directories to scan
     * @param bool $forceRescan Ignore cache and rescan
     *
     * @return self
     */
    public function discover(array $directories, bool $forceRescan = false): self
    {
        $discovered = null;

        if (!$forceRescan && $this->cache !== null && $this->cache->has()) {
            $discovered = $this->cache->read();
        }

        if ($discovered === null) {
            $discovery = new CommandDiscovery();
            $discovered = $discovery->discover($directories);

            if ($this->cache !== null) {
                $this->cache->write($discovered);
            }
        }

        /**
         * @var array<string, class-string<SymfonyCommand>> $discovered
         */
        foreach ($discovered as $name => $class) {
            $this->commandMap[$name] = $class;
        }

        $this->wireCommands();

        return $this;
    }

    /**
     * Register command classes by their class names.
     *
     * @param list<class-string<SymfonyCommand>> $classes
     *
     * @return Application
     */
    public function register(array $classes): self
    {
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(AsCommand::class);

            if ($attributes === []) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();

            $name = $attribute->name;

            if ($name === '') {
                continue;
            }

            $this->commandMap[$name] = $class;
        }

        $this->wireCommands();

        return $this;
    }

    /**
     * Add a command instance directly.
     *
     * @param SymfonyCommand $command
     *
     * @return Application
     */
    public function add(SymfonyCommand $command): self
    {
        $this->symfony->addCommand($command);

        return $this;
    }

    /**
     * Add an alternate name for a discovered command. Both names resolve to
     * the same class — the original name keeps working.
     *
     * Useful for shortcuts (`container:list` → `c:l`) and brand customisation.
     *
     * @param string $from
     * @param string $to
     *
     * @return Application
     */
    public function alias(string $from, string $to): self
    {
        if (!isset($this->commandMap[$from])) {
            throw new \InvalidArgumentException(sprintf('Cannot alias unknown command "%s".', $from));
        }

        if (isset($this->commandMap[$to]) && $this->commandMap[$to] !== $this->commandMap[$from]) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot alias "%s" → "%s": "%s" already exists for a different command.',
                $from,
                $to,
                $to,
            ));
        }

        $this->commandMap[$to] = $this->commandMap[$from];
        $this->wireCommands();

        return $this;
    }

    /**
     * Replace a command's name. The old name no longer resolves; only the new
     * name works. Useful for collision resolution between packages.
     *
     * @param string $from
     * @param string $to
     *
     * @return Application
     */
    public function rename(string $from, string $to): self
    {
        if (!isset($this->commandMap[$from])) {
            throw new \InvalidArgumentException(sprintf('Cannot rename unknown command "%s".', $from));
        }

        if (isset($this->commandMap[$to])) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot rename "%s" → "%s": "%s" already exists.',
                $from,
                $to,
                $to,
            ));
        }

        $this->commandMap[$to] = $this->commandMap[$from];
        unset($this->commandMap[$from]);

        $this->modifications[$to] = ($this->modifications[$from] ?? []) + ['name' => $to];

        if (isset($this->modifications[$from])) {
            unset($this->modifications[$from]);
        }

        $this->wireCommands();

        return $this;
    }

    /**
     * Override a command's metadata (description, help). Does not change the
     * command's name — use `rename()` for that.
     *
     * @param ?string $description
     * @param ?string $help
     * @param string $name
     *
     * @return Application
     */
    public function override(string $name, ?string $description = null, ?string $help = null): self
    {
        if (!isset($this->commandMap[$name])) {
            throw new \InvalidArgumentException(sprintf('Cannot override unknown command "%s".', $name));
        }

        $existing = $this->modifications[$name] ?? [];

        if ($description !== null) {
            $existing['description'] = $description;
        }
        if ($help !== null) {
            $existing['help'] = $help;
        }

        $this->modifications[$name] = $existing;
        $this->wireCommands();

        return $this;
    }

    /**
     * Run the application.
     *
     * @param ?InputInterface $input
     * @param ?OutputInterface $output
     *
     * @return int
     */
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        return $this->symfony->run($input, $output);
    }

    /**
     * Call a command programmatically and return its exit code.
     *
     * @param array<string, mixed> $arguments
     * @param string $commandName
     * @param ?OutputInterface $output
     *
     * @return int
     */
    public function call(string $commandName, array $arguments = [], ?OutputInterface $output = null): int
    {
        $command = $this->symfony->find($commandName);
        $input = new ArrayInput($arguments);

        return $command->run($input, $output ?? new BufferedOutput());
    }

    /**
     * Get the underlying Symfony Application instance.
     *
     * @return SymfonyApplication
     */
    public function getSymfonyApplication(): SymfonyApplication
    {
        return $this->symfony;
    }

    /**
     * Wire commands.
     *
     * @return void
     */
    private function wireCommands(): void
    {
        if ($this->container !== null) {
            $loader = new ContainerCommandLoader($this->container, $this->commandMap, $this->modifications);
            $this->symfony->setCommandLoader($loader);

            return;
        }

        $instances = [];

        foreach ($this->commandMap as $name => $class) {
            $command = new $class();
            $command->setName($name);
            $this->applyModifications($name, $command);
            $instances[$name] = $command;
        }

        $this->symfony->setCommandLoader(new InstanceCommandLoader($instances));
    }

    /**
     * Apply modifications.
     *
     * @param string $name
     * @param SymfonyCommand $command
     *
     * @return void
     */
    private function applyModifications(string $name, SymfonyCommand $command): void
    {
        if (!isset($this->modifications[$name])) {
            return;
        }

        $mod = $this->modifications[$name];

        if (isset($mod['name'])) {
            $command->setName($mod['name']);
        }
        if (isset($mod['description'])) {
            $command->setDescription($mod['description']);
        }
        if (isset($mod['help'])) {
            $command->setHelp($mod['help']);
        }
    }
}
