<?php

declare(strict_types=1);

/**
 * A single line of source code within a stack frame snippet.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Context;

final readonly class CodeLine
{
    /**
     * One line of source code within a stack-frame snippet.
     *
     * @param int $lineNumber 1-based line number in the source file
     * @param string $code Source text of the line
     * @param bool $isHighlighted Whether this is the line the error was thrown on
     */
    public function __construct(
        public int $lineNumber,
        public string $code,
        public bool $isHighlighted,
    ) {}
}
