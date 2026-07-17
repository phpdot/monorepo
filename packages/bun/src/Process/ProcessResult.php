<?php

declare(strict_types=1);

/**
 * Result of a one-shot subprocess invocation.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Process;

final readonly class ProcessResult
{
    /**
     * Hold a finished process outcome: exit code, stdout, and stderr.
     *
     * @param int $exitCode
     * @param string $stdout
     * @param string $stderr
     */
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {}

    /**
     * Whether the process exited with a zero status code.
     *
     * @return bool
     */
    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * Combined stdout + stderr (some tools, e.g. `ldd`, report on stderr).
     *
     * @return string
     */
    public function output(): string
    {
        return $this->stdout . $this->stderr;
    }
}
