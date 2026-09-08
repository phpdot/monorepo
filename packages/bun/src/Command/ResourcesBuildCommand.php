<?php

declare(strict_types=1);

/**
 * bun:resources:build — run the developer's recipe through the asset pipeline.
 *
 * The developer owns WHAT (resources/build.php declares entrypoints); the
 * package owns HOW (clean, css, bundle, charset — dependency-driven stages).
 * `--watch` is the dev loop: stable names, linked sourcemaps, the tools' own
 * watchers composed — the platform never watches files itself.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Command;

use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Exception\RecipeException;
use PHPdot\Bun\Resources\AssetPipeline;
use PHPdot\Bun\Resources\Recipe;
use PHPdot\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'bun:resources:build', description: 'Run the asset recipe (resources/build.php) through the pipeline.')]
final class ResourcesBuildCommand extends Command
{
    /**
     * No coroutine scheduler: this command orchestrates long-lived child
     * processes with pcntl signal forwarding, and the scheduler tears down
     * hooked pipe streams on SIGINT while a child is mid-read.
     */
    protected bool $coroutine = false;

    /**
     * @param AssetPipeline $pipeline The stage engine
     * @param BunConfig $config Names the resources directory the recipe lives in
     */
    public function __construct(
        private readonly AssetPipeline $pipeline,
        private readonly BunConfig $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('watch', null, InputOption::VALUE_NONE, "Run the recipe in watch mode — the tools' native watchers; Ctrl+C to stop");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $recipePath = $this->config->resourcesDir . '/build.php';

        if (!is_file($recipePath)) {
            $this->comment($output, "No asset recipe ({$recipePath}) — nothing to build.");

            return self::SUCCESS;
        }

        $recipe = new Recipe($this->config->homeDir);
        $registrar = require $recipePath;

        if (!is_callable($registrar)) {
            throw RecipeException::notCallable($recipePath);
        }

        $registrar($recipe);

        $watch = (bool) $input->getOption('watch');

        if ($watch) {
            $this->comment($output, 'Running the pipeline in watch mode — Ctrl+C to stop.');
        }

        return $this->pipeline->build($recipe, $watch);
    }
}
