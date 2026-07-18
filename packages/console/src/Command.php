<?php

declare(strict_types=1);

/**
 * Base console command — a Symfony Console command with PHPdot conveniences.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Console;

use Swoole\Coroutine;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

abstract class Command extends SymfonyCommand
{
    /**
     * Whether this command executes inside a coroutine scheduler.
     *
     * Swoole-native runtimes require coroutine context for connection pools
     * (channel operations) and for the compiled pdo_pgsql driver, which only
     * serves connections from inside a coroutine. Wrapping execution in
     * \Swoole\Coroutine\run() gives CLI code the same execution model as a
     * server request, so the same container bindings (scoped connections,
     * pools) work unchanged. Commands that start their own scheduler — a
     * Swoole server cannot boot inside a coroutine — opt out by setting
     * this to false.
     */
    protected bool $coroutine = true;

    /**
     * {@inheritDoc}
     *
     * Executes inside a coroutine scheduler unless the command opted out,
     * Swoole is unavailable, or a coroutine is already active. Throwables
     * are carried out of the scheduler and rethrown, so Symfony's error
     * rendering and exit-code semantics are identical in both modes.
     *
     * Hooks everything except native curl: curl-multi handles owned by
     * long-lived HTTP clients (e.g. symfony/http-client) are created before
     * the scheduler starts, and Swoole's native-curl hook throws on handles
     * it did not create. Server-side hook flags are configured separately
     * via ServerConfig::$hookFlags and are not affected by this.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->coroutine || !extension_loaded('swoole') || Coroutine::getCid() > 0) {
            return parent::run($input, $output);
        }

        $exit = self::FAILURE;
        $thrown = null;

        Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL & ~SWOOLE_HOOK_NATIVE_CURL]);

        Coroutine\run(function () use ($input, $output, &$exit, &$thrown): void {
            try {
                $exit = parent::run($input, $output);
            } catch (\Throwable $e) {
                $thrown = $e;
            }
        });

        if ($thrown instanceof \Throwable) {
            throw $thrown;
        }

        return $exit;
    }

    /**
     * Write an info message to output.
     *
     * @param string $message
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function info(OutputInterface $output, string $message): void
    {
        (new ConsoleStyle($output))->info($message);
    }

    /**
     * Write an error message to output.
     *
     * @param OutputInterface $output
     * @param string $message
     *
     * @return void
     */
    protected function error(OutputInterface $output, string $message): void
    {
        (new ConsoleStyle($output))->error($message);
    }

    /**
     * Write a success message to output with a checkmark prefix.
     *
     * @param OutputInterface $output
     * @param string $message
     *
     * @return void
     */
    protected function success(OutputInterface $output, string $message): void
    {
        (new ConsoleStyle($output))->success($message);
    }

    /**
     * Write a warning message to output with a warning prefix.
     *
     * @param OutputInterface $output
     * @param string $message
     *
     * @return void
     */
    protected function warning(OutputInterface $output, string $message): void
    {
        (new ConsoleStyle($output))->warning($message);
    }

    /**
     * Write a comment message to output.
     *
     * @param OutputInterface $output
     * @param string $message
     *
     * @return void
     */
    protected function comment(OutputInterface $output, string $message): void
    {
        (new ConsoleStyle($output))->comment($message);
    }

    /**
     * Ask the user a question.
     *
     * @param string $question
     * @param ?string $default
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return ?string
     */
    protected function ask(InputInterface $input, OutputInterface $output, string $question, ?string $default = null): ?string
    {
        $helper = $this->getQuestionHelper();
        $q = new Question($question . ' ', $default);

        /**
         * @var ?string $answer
         */
        $answer = $helper->ask($input, $output, $q);

        return $answer;
    }

    /**
     * Ask the user a yes/no confirmation question.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $question
     * @param bool $default
     *
     * @return bool
     */
    protected function confirm(InputInterface $input, OutputInterface $output, string $question, bool $default = false): bool
    {
        $helper = $this->getQuestionHelper();
        $q = new ConfirmationQuestion($question . ' ', $default);

        /**
         * @var bool $answer
         */
        $answer = $helper->ask($input, $output, $q);

        return $answer;
    }

    /**
     * Ask the user to choose from a list of options.
     *
     * @param list<string> $choices
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $question
     * @param ?string $default
     *
     * @return string
     */
    protected function choice(InputInterface $input, OutputInterface $output, string $question, array $choices, ?string $default = null): string
    {
        $helper = $this->getQuestionHelper();
        $q = new ChoiceQuestion($question, $choices, $default);

        /**
         * @var string $answer
         */
        $answer = $helper->ask($input, $output, $q);

        return $answer;
    }

    /**
     * Ask the user to choose multiple items from a list of options.
     *
     * @param list<string> $choices
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $question
     *
     * @return list<string>
     */
    protected function multiChoice(InputInterface $input, OutputInterface $output, string $question, array $choices): array
    {
        $helper = $this->getQuestionHelper();
        $q = new ChoiceQuestion($question, $choices);
        $q->setMultiselect(true);

        /**
         * @var list<string> $answer
         */
        $answer = $helper->ask($input, $output, $q);

        return $answer;
    }

    /**
     * Ask the user for secret input (hidden).
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $question
     *
     * @return ?string
     */
    protected function secret(InputInterface $input, OutputInterface $output, string $question): ?string
    {
        $helper = $this->getQuestionHelper();
        $q = new Question($question . ' ');
        $q->setHidden(true);
        $q->setHiddenFallback(false);

        /**
         * @var ?string $answer
         */
        $answer = $helper->ask($input, $output, $q);

        return $answer;
    }

    /**
     * Ask the user a question with autocomplete suggestions.
     *
     * @param list<string>|callable(string): list<string> $suggestions
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $question
     *
     * @return string
     */
    protected function autocomplete(InputInterface $input, OutputInterface $output, string $question, array|callable $suggestions): string
    {
        $helper = $this->getQuestionHelper();
        $q = new Question($question . ' ');

        if (is_callable($suggestions)) {
            $q->setAutocompleterCallback($suggestions);
        } else {
            $q->setAutocompleterValues($suggestions);
        }

        /**
         * @var string $answer
         */
        $answer = $helper->ask($input, $output, $q);

        return $answer;
    }

    /**
     * Render a table to output.
     *
     * @param list<array<string, scalar|null>> $rows
     * @param list<string> $headers
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function table(OutputInterface $output, array $rows, array $headers = []): void
    {
        if ($rows === []) {
            return;
        }

        if ($headers === []) {
            $headers = array_keys($rows[0]);
        }

        $table = new Table($output);
        $table->setHeaders($headers);

        foreach ($rows as $row) {
            $table->addRow(array_values($row));
        }

        $table->render();
    }

    /**
     * Iterate over items with a progress bar.
     *
     * @template T
     *
     * @param iterable<T> $items
     * @param callable(T, int): void $callback
     * @param int|null $total Total steps (required for generators, auto-detected for arrays/Countable)
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function withProgress(OutputInterface $output, iterable $items, callable $callback, ?int $total = null): void
    {
        $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output);

        if ($total !== null) {
            $progressBar->setMaxSteps($total);
        } elseif (is_array($items) || $items instanceof \Countable) {
            $progressBar->setMaxSteps(count($items));
        }

        $progressBar->start();

        $index = 0;

        foreach ($items as $item) {
            $callback($item, $index);
            $progressBar->advance();
            $index++;
        }

        $progressBar->finish();
        $output->writeln('');
    }

    /**
     * Get question helper.
     *
     * @return QuestionHelper
     */
    private function getQuestionHelper(): QuestionHelper
    {
        /**
         * @var QuestionHelper $helper
         */
        $helper = $this->getHelper('question');

        return $helper;
    }
}
