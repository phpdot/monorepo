<?php

declare(strict_types=1);

/**
 * Runs an installed CLI tool via `bun x`. This is the supported path for build-step tools
 * (obfuscator, tailwind, postcss, …): install them with bun:install, then run them here.
 *
 * Flags for the tool must follow `--` so the console does not parse them, e.g.
 * `dot bun:x javascript-obfuscator app.js -- --output app.obf.js`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Command;

use PHPdot\Bun\Bun;
use PHPdot\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'bun:x',
    description: 'Run an installed CLI tool (bun x).',
)]
final class XCommand extends Command
{
    /**
     * Inject the Bun service the command drives.
     *
     * @param Bun $bun
     */
    public function __construct(
        private readonly Bun $bun,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tool', InputArgument::REQUIRED, 'CLI tool to run')
            ->addArgument('args', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Arguments forwarded to the tool (put tool flags after --)')
            ->setHelp('Pass flags to the tool after a "--" separator, e.g. <info>bun:x prettier src -- --write</info>.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /**
         * @var string $tool
         */
        $tool = $input->getArgument('tool');
        /**
         * @var list<string> $args
         */
        $args = $input->getArgument('args');

        return $this->bun->x($tool, $args);
    }
}
