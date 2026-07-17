<?php

declare(strict_types=1);

namespace PHPdot\ErrorHandler\Tests\Unit\Context;

use PHPdot\ErrorHandler\Context\ContextTab;
use PHPdot\ErrorHandler\Context\ErrorContext;
use PHPdot\ErrorHandler\Context\StackTrace;
use PHPdot\ErrorHandler\Solution\Solution;
use PHPdot\Http\Message\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ErrorContextTest extends TestCase
{
    #[Test]
    public function storesException(): void
    {
        $exception = new \RuntimeException('test');
        $context = $this->makeContext(exception: $exception);

        self::assertSame($exception, $context->exception);
    }

    #[Test]
    public function storesStackTrace(): void
    {
        $trace = new StackTrace(frames: []);
        $context = $this->makeContext(stackTrace: $trace);

        self::assertSame($trace, $context->stackTrace);
    }

    #[Test]
    public function storesStatusCode(): void
    {
        $context = $this->makeContext(statusCode: 404);

        self::assertSame(404, $context->statusCode);
    }

    #[Test]
    public function storesRequest(): void
    {
        $request = new ServerRequest('GET', '/test');
        $context = $this->makeContext(request: $request);

        self::assertSame($request, $context->request);
    }

    #[Test]
    public function storesNullRequest(): void
    {
        $context = $this->makeContext(request: null);

        self::assertNull($context->request);
    }

    #[Test]
    public function storesEnvironment(): void
    {
        $env = ['APP_ENV' => 'testing', 'PHP_VERSION' => '8.5'];
        $context = $this->makeContext(environment: $env);

        self::assertSame($env, $context->environment);
    }

    #[Test]
    public function storesEmptyEnvironment(): void
    {
        $context = $this->makeContext(environment: []);

        self::assertSame([], $context->environment);
    }

    #[Test]
    public function storesContextTabs(): void
    {
        $tabs = [
            new ContextTab(label: 'Queries', data: ['count' => 5]),
            new ContextTab(label: 'Route', data: ['name' => 'home']),
        ];
        $context = $this->makeContext(contextTabs: $tabs);

        self::assertCount(2, $context->context);
        self::assertSame('Queries', $context->context[0]->label);
    }

    #[Test]
    public function storesSolutions(): void
    {
        $solutions = [
            new Solution(title: 'Fix it', description: 'Do this'),
        ];
        $context = $this->makeContext(solutions: $solutions);

        self::assertCount(1, $context->solutions);
        self::assertSame('Fix it', $context->solutions[0]->title);
    }

    #[Test]
    public function storesIsDevelopmentTrue(): void
    {
        $context = $this->makeContext(isDevelopment: true);

        self::assertTrue($context->isDevelopment);
    }

    #[Test]
    public function storesIsDevelopmentFalse(): void
    {
        $context = $this->makeContext(isDevelopment: false);

        self::assertFalse($context->isDevelopment);
    }

    #[Test]
    public function isReadonly(): void
    {
        $ref = new \ReflectionClass(ErrorContext::class);

        self::assertTrue($ref->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $ref = new \ReflectionClass(ErrorContext::class);

        self::assertTrue($ref->isFinal());
    }

    /**
     * @param list<ContextTab> $contextTabs
     * @param list<Solution> $solutions
     * @param array<string, string> $environment
     */
    private function makeContext(
        ?\Throwable $exception = null,
        ?StackTrace $stackTrace = null,
        int $statusCode = 500,
        mixed $request = 'NOT_SET',
        array $environment = [],
        array $contextTabs = [],
        array $solutions = [],
        bool $isDevelopment = true,
    ): ErrorContext {
        return new ErrorContext(
            exception: $exception ?? new \RuntimeException('test'),
            stackTrace: $stackTrace ?? new StackTrace(frames: []),
            statusCode: $statusCode,
            request: $request === 'NOT_SET' ? null : $request,
            environment: $environment,
            context: $contextTabs,
            solutions: $solutions,
            isDevelopment: $isDevelopment,
        );
    }
}
