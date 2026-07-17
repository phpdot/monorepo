<?php

declare(strict_types=1);

/**
 * The single chokepoint for invoking an executable as a subprocess.
 *
 * Deliberately binary-agnostic: it knows how to run *an* executable with arguments, not how to
 * find the Bun binary. This keeps the subprocess layer free of any dependency on
 * {@see \PHPdot\Bun\Runtime\BinaryResolver} (which itself uses this class to probe `--version`),
 * so there is no circular dependency.
 *
 * Plain blocking I/O. Under Swoole with coroutine runtime hooks enabled, symfony/process'
 * proc_open is transparently made coroutine-aware; outside Swoole it runs as an ordinary
 * blocking subprocess.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Process;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;
use Symfony\Component\Process\Process;

#[Singleton]
#[Binds(ProcessRunnerInterface::class)]
final class BunProcess implements ProcessRunnerInterface
{
    /**
     * Run an executable to completion and capture its output.
     *
     * @param list<string> $args
     */
    public function run(string $executable, array $args = [], ?string $cwd = null): ProcessResult
    {
        $process = new Process([$executable, ...$args], $cwd);
        $process->setTimeout(null);
        $process->run();

        return new ProcessResult(
            $process->getExitCode() ?? -1,
            $process->getOutput(),
            $process->getErrorOutput(),
        );
    }

    /**
     * Run an executable, streaming output live and forwarding SIGINT/SIGTERM to the child.
     *
     * @param list<string> $args
     */
    public function passthrough(string $executable, array $args = [], ?string $cwd = null): int
    {
        $process = new Process([$executable, ...$args], $cwd);
        $process->setTimeout(null);

        $stdout = fopen('php://stdout', 'wb');
        $stderr = fopen('php://stderr', 'wb');
        if ($stdout === false || $stderr === false) {
            $process->run();

            return $process->getExitCode() ?? 1;
        }

        try {
            $process->start(static function (string $type, string $buffer) use ($stdout, $stderr): void {
                fwrite($type === Process::ERR ? $stderr : $stdout, $buffer);
            });

            $this->forwardSignals($process);

            return $process->wait();
        } finally {
            fclose($stdout);
            fclose($stderr);
        }
    }

    /**
     * Forward termination signals from this process to the child so long-lived processes shut down
     * cleanly. No-op without ext-pcntl (e.g. Windows), where signals reach the child via the OS.
     *
     * @param Process $process
     *
     * @return void
     */
    private function forwardSignals(Process $process): void
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);
        $handler = static function (int $signal) use ($process): void {
            if ($process->isRunning()) {
                $process->signal($signal);
            }
        };
        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGTERM, $handler);
    }
}
