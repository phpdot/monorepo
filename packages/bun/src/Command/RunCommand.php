<?php

declare(strict_types=1);

/**
 * Runs a package.json script via `bun run`. May be long-lived (a dev server): output streams
 * through and SIGINT/SIGTERM are forwarded to the child.
 *
 * Flags for the script must follow `--` so the console does not parse them, e.g.
 * `dot bun:run dev -- --port 3000`.
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
    name: 'bun:run',
    description: 'Run a package.json script (bun run).',
)]
final class RunCommand extends Command
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
            ->addArgument('script', InputArgument::REQUIRED, 'Script name to run')
            ->addArgument('args', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Arguments forwarded to the script (put script flags after --)')
            ->setHelp('Pass flags to the script after a "--" separator, e.g. <info>bun:run dev -- --port 3000</info>.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /**
         * @var string $script
         */
        $script = $input->getArgument('script');
        /**
         * @var list<string> $args
         */
        $args = $input->getArgument('args');

        return $this->bun->run($script, $args);
    }
}
