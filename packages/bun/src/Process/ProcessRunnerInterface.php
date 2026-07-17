<?php

declare(strict_types=1);

/**
 * Runs an executable as a subprocess. The single seam through which the package invokes external
 * binaries, so callers never depend on the concrete runtime ({@see BunProcess}).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Process;

interface ProcessRunnerInterface
{
    /**
     * Run an executable to completion and capture its output. For one-shot, parseable invocations.
     *
     * @param list<string> $args
     * @param string $executable
     * @param ?string $cwd
     *
     * @return ProcessResult
     */
    public function run(string $executable, array $args = [], ?string $cwd = null): ProcessResult;

    /**
     * Run an executable, streaming its stdout/stderr live to the console and forwarding termination
     * signals to the child. For passthrough and long-lived processes (dev server, build --watch).
     * Returns the child's exit code.
     *
     * @param list<string> $args
     * @param string $executable
     * @param ?string $cwd
     *
     * @return int
     */
    public function passthrough(string $executable, array $args = [], ?string $cwd = null): int;
}
