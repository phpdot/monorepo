<?php

declare(strict_types=1);

/**
 * A single stack frame with file, line, call info, and code snippet.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Context;

final readonly class Frame
{
    /**
     * One stack frame with its file, line, call info, and code snippet.
     *
     * @param string $file Absolute file path
     * @param int $line Line number
     * @param string|null $class Class name (null for functions)
     * @param string|null $function Function/method name
     * @param list<CodeLine> $codeSnippet Code lines around the error
     * @param bool $isApplication True if application code, false if vendor
     */
    public function __construct(
        public string $file,
        public int $line,
        public ?string $class,
        public ?string $function,
        public array $codeSnippet,
        public bool $isApplication,
    ) {}
}
