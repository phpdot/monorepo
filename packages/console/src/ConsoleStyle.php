<?php

declare(strict_types=1);

/**
 * ConsoleStyle — styled message helpers over an OutputInterface, usable anywhere output is available
 * (commands, listeners, services), not only inside a Command subclass. The Command helpers forward
 * here, so the styling is defined in one place.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Console;

use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleStyle
{
    /**
     * __construct.
     *
     * @param OutputInterface $output
     */
    public function __construct(
        private readonly OutputInterface $output,
    ) {}

    /**
     * Write an info message.
     *
     * @param string $message
     *
     * @return void
     */
    public function info(string $message): void
    {
        $this->output->writeln('<info>' . $message . '</info>');
    }

    /**
     * Write an error message.
     *
     * @param string $message
     *
     * @return void
     */
    public function error(string $message): void
    {
        $this->output->writeln('<error>' . $message . '</error>');
    }

    /**
     * Write a success message with a checkmark prefix.
     *
     * @param string $message
     *
     * @return void
     */
    public function success(string $message): void
    {
        $this->output->writeln('<info>✔ ' . $message . '</info>');
    }

    /**
     * Write a warning message with a warning prefix.
     *
     * @param string $message
     *
     * @return void
     */
    public function warning(string $message): void
    {
        $this->output->writeln('<comment>⚠ ' . $message . '</comment>');
    }

    /**
     * Write a comment message.
     *
     * @param string $message
     *
     * @return void
     */
    public function comment(string $message): void
    {
        $this->output->writeln('<comment>' . $message . '</comment>');
    }
}
