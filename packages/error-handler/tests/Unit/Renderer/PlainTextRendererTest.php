<?php

declare(strict_types=1);

namespace PHPdot\ErrorHandler\Tests\Unit\Renderer;

use PHPdot\ErrorHandler\Context\ErrorContext;
use PHPdot\ErrorHandler\Context\Frame;
use PHPdot\ErrorHandler\Context\StackTrace;
use PHPdot\ErrorHandler\Renderer\PlainTextRenderer;
use PHPdot\ErrorHandler\Solution\Solution;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PlainTextRendererTest extends TestCase
{
    #[Test]
    public function outputContainsExceptionClass(): void
    {
        $output = $this->render($this->makeContext());

        self::assertStringContainsString('RuntimeException', $output);
    }

    #[Test]
    public function outputContainsExceptionMessage(): void
    {
        $output = $this->render($this->makeContext(message: 'Something broke'));

        self::assertStringContainsString('Something broke', $output);
    }

    #[Test]
    public function outputContainsFileAndLine(): void
    {
        $context = $this->makeContext();
        $output = $this->render($context);
        $file = $context->exception->getFile();
        $line = $context->exception->getLine();

        self::assertStringContainsString($file, $output);
        self::assertStringContainsString((string) $line, $output);
    }

    #[Test]
    public function devModeIncludesStackTrace(): void
    {
        $frames = [
            $this->makeFrame('/app/src/Foo.php', 10, 'App\\Foo', 'bar'),
            $this->makeFrame('/app/src/index.php', 5, null, 'main'),
        ];
        $output = $this->render($this->makeContext(isDevelopment: true, frames: $frames));

        self::assertStringContainsString('Stack trace:', $output);
        self::assertStringContainsString('#0', $output);
        self::assertStringContainsString('#1', $output);
        self::assertStringContainsString('/app/src/Foo.php:10', $output);
        self::assertStringContainsString('App\\Foo::bar()', $output);
    }

    #[Test]
    public function prodModeExcludesStackTrace(): void
    {
        $frames = [
            $this->makeFrame('/app/src/Foo.php', 10, 'App\\Foo', 'bar'),
        ];
        $output = $this->render($this->makeContext(isDevelopment: false, frames: $frames));

        self::assertStringNotContainsString('Stack trace:', $output);
        self::assertStringNotContainsString('#0', $output);
    }

    #[Test]
    public function devModeIncludesSolutions(): void
    {
        $solutions = [
            new Solution(title: 'Fix this', description: 'Run composer install'),
        ];
        $output = $this->render($this->makeContext(isDevelopment: true, solutions: $solutions));

        self::assertStringContainsString('Suggested solutions:', $output);
        self::assertStringContainsString('Fix this', $output);
        self::assertStringContainsString('Run composer install', $output);
    }

    #[Test]
    public function prodModeExcludesSolutions(): void
    {
        $solutions = [
            new Solution(title: 'Fix this', description: 'Run composer install'),
        ];
        $output = $this->render($this->makeContext(isDevelopment: false, solutions: $solutions));

        self::assertStringNotContainsString('Suggested solutions:', $output);
        self::assertStringNotContainsString('Fix this', $output);
    }

    #[Test]
    public function devModeWithNoSolutionsOmitsSolutionSection(): void
    {
        $output = $this->render($this->makeContext(isDevelopment: true, solutions: []));

        self::assertStringNotContainsString('Suggested solutions:', $output);
    }

    #[Test]
    public function frameWithClassAndFunction(): void
    {
        $frames = [
            $this->makeFrame('/app/src/Foo.php', 10, 'App\\Foo', 'bar'),
        ];
        $output = $this->render($this->makeContext(isDevelopment: true, frames: $frames));

        self::assertStringContainsString('App\\Foo::bar()', $output);
    }

    #[Test]
    public function frameWithFunctionOnly(): void
    {
        $frames = [
            $this->makeFrame('/app/src/helpers.php', 5, null, 'array_map'),
        ];
        $output = $this->render($this->makeContext(isDevelopment: true, frames: $frames));

        self::assertStringContainsString('array_map()', $output);
    }

    #[Test]
    public function frameWithNullClassAndNullFunction(): void
    {
        $frames = [
            $this->makeFrame('/app/index.php', 1, null, null),
        ];
        $output = $this->render($this->makeContext(isDevelopment: true, frames: $frames));

        self::assertStringContainsString('{main}()', $output);
    }

    #[Test]
    public function outputFormatBracketsExceptionClass(): void
    {
        $output = $this->render($this->makeContext());

        // Format: [RuntimeException] message in file:line
        self::assertMatchesRegularExpression('/\[RuntimeException\]/', $output);
    }

    #[Test]
    public function multipleSolutions(): void
    {
        $solutions = [
            new Solution(title: 'Fix A', description: 'Do A'),
            new Solution(title: 'Fix B', description: 'Do B'),
        ];
        $output = $this->render($this->makeContext(isDevelopment: true, solutions: $solutions));

        self::assertStringContainsString('Fix A: Do A', $output);
        self::assertStringContainsString('Fix B: Do B', $output);
    }

    #[Test]
    public function solutionFormatIsDashPrefixed(): void
    {
        $solutions = [
            new Solution(title: 'Fix', description: 'Do it'),
        ];
        $output = $this->render($this->makeContext(isDevelopment: true, solutions: $solutions));

        self::assertStringContainsString('  - Fix: Do it', $output);
    }

    #[Test]
    public function prodModeStillShowsBasicInfo(): void
    {
        $output = $this->render($this->makeContext(
            message: 'Error occurred',
            isDevelopment: false,
        ));

        // First line always shows exception class and message
        self::assertStringContainsString('[RuntimeException]', $output);
        self::assertStringContainsString('Error occurred', $output);
    }

    private function render(ErrorContext $context): string
    {
        return (new PlainTextRenderer())->render($context);
    }

    /**
     * @param list<Frame> $frames
     * @param list<Solution> $solutions
     */
    private function makeContext(
        string $message = 'Test error',
        bool $isDevelopment = true,
        array $frames = [],
        array $solutions = [],
    ): ErrorContext {
        return new ErrorContext(
            exception: new \RuntimeException($message),
            stackTrace: new StackTrace(frames: $frames),
            statusCode: 500,
            request: null,
            environment: [],
            context: [],
            solutions: $solutions,
            isDevelopment: $isDevelopment,
        );
    }

    private function makeFrame(
        string $file,
        int $line,
        ?string $class,
        ?string $function,
    ): Frame {
        return new Frame(
            file: $file,
            line: $line,
            class: $class,
            function: $function,
            codeSnippet: [],
            isApplication: true,
        );
    }
}
