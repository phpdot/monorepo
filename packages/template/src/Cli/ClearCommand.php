<?php

declare(strict_types=1);

/**
 * Removes every compiled template from the configured cache directory.
 *
 * @internal
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Template\Cli;

use PHPdot\Console\Command;
use PHPdot\Template\TemplateConfig;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'template:clear', description: 'Clear the compiled template cache (rebuilt on the next render).')]
final class ClearCommand extends Command
{
    /**
     * Touches only the filesystem — no pool, so a Swoole scheduler would only add latency.
     */
    protected bool $coroutine = false;

    public function __construct(private readonly TemplateConfig $config)
    {
        parent::__construct();
    }

    /**
     * An empty path disables caching exactly as it does in the engine, so it clears nothing.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = $this->config->cache;

        if ($directory === null || $directory === '') {
            $this->comment($output, 'Template cache is disabled (no cache path configured) — nothing to clear.');

            return Command::SUCCESS;
        }

        $removed = is_dir($directory) ? $this->clear($directory) : 0;

        if ($removed === 0) {
            $this->comment($output, sprintf('Template cache is already empty (%s).', $directory));

            return Command::SUCCESS;
        }

        $this->success($output, sprintf(
            'Template cache cleared: %d compiled template%s removed from %s — restart running workers to recompile.',
            $removed,
            $removed === 1 ? '' : 's',
            $directory,
        ));

        return Command::SUCCESS;
    }

    /**
     * Removes only what Twig writes — two-hex buckets of hash-named .php files — so a
     * misconfigured path cannot cost application code. A bucket left empty goes with its files.
     *
     * @throws RuntimeException When a compiled template cannot be removed.
     */
    private function clear(string $directory): int
    {
        $removed = 0;

        foreach ($this->entries($directory) as $bucket) {
            $bucketPath = $directory . DIRECTORY_SEPARATOR . $bucket;

            if (preg_match('/^[0-9a-f]{2}$/', $bucket) !== 1 || !is_dir($bucketPath)) {
                continue;
            }

            foreach ($this->entries($bucketPath) as $file) {
                $path = $bucketPath . DIRECTORY_SEPARATOR . $file;

                if (preg_match('/^[0-9a-f]{32}\.php$/', $file) !== 1 || !is_file($path)) {
                    continue;
                }

                if (!unlink($path)) {
                    throw new RuntimeException("Could not remove the compiled template {$path}.");
                }

                $removed++;
            }

            if ($this->entries($bucketPath) === []) {
                rmdir($bucketPath);
            }
        }

        return $removed;
    }

    /**
     * @return list<string> The directory's entries without the dot entries, or nothing when unreadable.
     */
    private function entries(string $directory): array
    {
        $entries = scandir($directory);

        if ($entries === false) {
            return [];
        }

        return array_values(array_filter(
            $entries,
            static fn(string $entry): bool => $entry !== '.' && $entry !== '..',
        ));
    }
}
