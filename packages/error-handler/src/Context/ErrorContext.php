<?php

declare(strict_types=1);

/**
 * All data needed to render an error page.
 *
 * This is the contract between the data pipeline and the renderer.
 * Any renderer gets the same structured data.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Context;

use Psr\Http\Message\ServerRequestInterface;

final readonly class ErrorContext
{
    /**
     * All structured data a renderer needs to draw one error page.
     *
     * @param \Throwable $exception The original exception
     * @param StackTrace $stackTrace Parsed stack trace with code snippets
     * @param int $statusCode HTTP status code
     * @param ServerRequestInterface|null $request PSR-7 request (null in standalone mode)
     * @param array<string, string> $environment Filtered environment variables
     * @param list<ContextTab> $context Extra debug tabs from context providers
     * @param list<\PHPdot\ErrorHandler\Solution\Solution> $solutions Suggested fixes
     * @param bool $isDevelopment Whether this is a development environment
     */
    public function __construct(
        public \Throwable $exception,
        public StackTrace $stackTrace,
        public int $statusCode,
        public ?ServerRequestInterface $request,
        public array $environment,
        public array $context,
        public array $solutions,
        public bool $isDevelopment,
    ) {}
}
