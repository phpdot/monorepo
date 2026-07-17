<?php

declare(strict_types=1);

namespace PHPdot\ErrorHandler\Tests\Unit\Renderer;

use PHPdot\ErrorHandler\Context\ErrorContext;
use PHPdot\ErrorHandler\Context\Frame;
use PHPdot\ErrorHandler\Context\StackTrace;
use PHPdot\ErrorHandler\Renderer\JsonRenderer;
use PHPdot\ErrorHandler\Solution\Solution;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonRendererTest extends TestCase
{
    #[Test]
    public function outputIsValidJson(): void
    {
        $renderer = new JsonRenderer();
        $context = $this->makeContext();

        $output = $renderer->render($context);
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
    }

    #[Test]
    public function outputContainsTypeField(): void
    {
        $renderer = new JsonRenderer();
        $decoded = $this->renderAndDecode($this->makeContext());

        self::assertSame('about:blank', $decoded['type']);
    }

    #[Test]
    public function outputContainsTitleField(): void
    {
        $renderer = new JsonRenderer();
        $decoded = $this->renderAndDecode($this->makeContext(statusCode: 500));

        self::assertSame('Internal Server Error', $decoded['title']);
    }

    #[Test]
    public function outputContainsStatusField(): void
    {
        $renderer = new JsonRenderer();
        $decoded = $this->renderAndDecode($this->makeContext(statusCode: 404));

        self::assertSame(404, $decoded['status']);
    }

    #[Test]
    public function devModeShowsExceptionMessage(): void
    {
        $renderer = new JsonRenderer();
        $decoded = $this->renderAndDecode($this->makeContext(
            message: 'Something broke badly',
            isDevelopment: true,
        ));

        self::assertSame('Something broke badly', $decoded['detail']);
    }

    #[Test]
    public function prodModeShowsSafeMessage(): void
    {
        $renderer = new JsonRenderer();
        $decoded = $this->renderAndDecode($this->makeContext(
            message: 'Secret internal error',
            statusCode: 500,
            isDevelopment: false,
        ));

        self::assertSame('An unexpected error occurred.', $decoded['detail']);
        self::assertStringNotContainsString('Secret', $decoded['detail']);
    }

    #[Test]
    public function devModeIncludesExceptionBlock(): void
    {
        $renderer = new JsonRenderer();
        $decoded = $this->renderAndDecode($this->makeContext(isDevelopment: true));

        self::assertArrayHasKey('exception', $decoded);
        self::assertArrayHasKey('class', $decoded['exception']);
        self::assertArrayHasKey('message', $decoded['exception']);
        self::assertArrayHasKey('file', $decoded['exception']);
        self::assertArrayHasKey('line', $decoded['exception']);
        self::assertArrayHasKey('trace', $decoded['exception']);
    }

    #[Test]
    public function prodModeExcludesExceptionBlock(): void
    {
        $renderer = new JsonRenderer();
        $decoded = $this->renderAndDecode($this->makeContext(isDevelopment: false));

        self::assertArrayNotHasKey('exception', $decoded);
    }

    #[Test]
    public function devModeIncludesTraceInExceptionBlock(): void
    {
        $frames = [
            new Frame(
                file: '/app/src/Foo.php',
                line: 10,
                class: 'App\\Foo',
                function: 'bar',
                codeSnippet: [],
                isApplication: true,
            ),
        ];
        $context = $this->makeContext(isDevelopment: true, frames: $frames);
        $decoded = $this->renderAndDecode($context);

        self::assertCount(1, $decoded['exception']['trace']);
        self::assertSame('/app/src/Foo.php', $decoded['exception']['trace'][0]['file']);
        self::assertSame(10, $decoded['exception']['trace'][0]['line']);
        self::assertSame('App\\Foo', $decoded['exception']['trace'][0]['class']);
        self::assertSame('bar', $decoded['exception']['trace'][0]['function']);
    }

    #[Test]
    public function devModeIncludesSolutions(): void
    {
        $solutions = [
            new Solution(title: 'Run migrations', description: 'Execute php artisan migrate'),
        ];
        $context = $this->makeContext(isDevelopment: true, solutions: $solutions);
        $decoded = $this->renderAndDecode($context);

        self::assertArrayHasKey('solutions', $decoded);
        self::assertCount(1, $decoded['solutions']);
        self::assertSame('Run migrations', $decoded['solutions'][0]['title']);
        self::assertSame('Execute php artisan migrate', $decoded['solutions'][0]['description']);
    }

    #[Test]
    public function devModeWithoutSolutionsOmitsSolutionsKey(): void
    {
        $context = $this->makeContext(isDevelopment: true, solutions: []);
        $decoded = $this->renderAndDecode($context);

        self::assertArrayNotHasKey('solutions', $decoded);
    }

    #[Test]
    public function prodModeOmitsSolutions(): void
    {
        $solutions = [new Solution(title: 'Fix', description: 'Do it')];
        $context = $this->makeContext(isDevelopment: false, solutions: $solutions);
        $decoded = $this->renderAndDecode($context);

        self::assertArrayNotHasKey('solutions', $decoded);
    }

    #[Test]
    #[DataProvider('statusCodeTitleProvider')]
    public function correctTitleForStatusCode(int $statusCode, string $expectedTitle): void
    {
        $renderer = new JsonRenderer();
        $decoded = $this->renderAndDecode($this->makeContext(statusCode: $statusCode));

        self::assertSame($expectedTitle, $decoded['title']);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function statusCodeTitleProvider(): iterable
    {
        yield '400' => [400, 'Bad Request'];
        yield '401' => [401, 'Unauthorized'];
        yield '403' => [403, 'Forbidden'];
        yield '404' => [404, 'Not Found'];
        yield '405' => [405, 'Method Not Allowed'];
        yield '409' => [409, 'Conflict'];
        yield '422' => [422, 'Unprocessable Entity'];
        yield '429' => [429, 'Too Many Requests'];
        yield '500' => [500, 'Internal Server Error'];
        yield '502' => [502, 'Bad Gateway'];
        yield '503' => [503, 'Service Unavailable'];
    }

    #[Test]
    public function unknownStatusCodeGetsFallbackTitle(): void
    {
        $decoded = $this->renderAndDecode($this->makeContext(statusCode: 418));

        self::assertSame('Error', $decoded['title']);
    }

    #[Test]
    #[DataProvider('prodSafeMessageProvider')]
    public function correctSafeMessageForStatusCode(int $statusCode, string $expectedMessage): void
    {
        $decoded = $this->renderAndDecode($this->makeContext(
            statusCode: $statusCode,
            isDevelopment: false,
        ));

        self::assertSame($expectedMessage, $decoded['detail']);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function prodSafeMessageProvider(): iterable
    {
        yield '400' => [400, 'The request could not be understood.'];
        yield '401' => [401, 'Authentication is required.'];
        yield '403' => [403, 'You do not have permission to access this resource.'];
        yield '404' => [404, 'The requested resource was not found.'];
        yield '405' => [405, 'The request method is not supported.'];
        yield '422' => [422, 'The request data is invalid.'];
        yield '429' => [429, 'Too many requests. Please try again later.'];
        yield '500' => [500, 'An unexpected error occurred.'];
        yield '503' => [503, 'The service is temporarily unavailable.'];
    }

    #[Test]
    public function unknownStatusCodeGetsFallbackSafeMessage(): void
    {
        $decoded = $this->renderAndDecode($this->makeContext(
            statusCode: 418,
            isDevelopment: false,
        ));

        self::assertSame('An error occurred.', $decoded['detail']);
    }

    #[Test]
    public function exceptionClassIsCorrectInDevMode(): void
    {
        $decoded = $this->renderAndDecode($this->makeContext(isDevelopment: true));

        self::assertSame(\RuntimeException::class, $decoded['exception']['class']);
    }

    #[Test]
    public function outputHasPrettyPrint(): void
    {
        $renderer = new JsonRenderer();
        $output = $renderer->render($this->makeContext());

        // Pretty print adds newlines
        self::assertStringContainsString("\n", $output);
    }

    #[Test]
    public function outputDoesNotEscapeSlashes(): void
    {
        $renderer = new JsonRenderer();
        $context = $this->makeContext(isDevelopment: true);
        $output = $renderer->render($context);

        // File paths should have unescaped slashes
        self::assertStringNotContainsString('\\/', $output);
    }

    #[Test]
    public function multipleSolutionsInDevMode(): void
    {
        $solutions = [
            new Solution(title: 'Fix A', description: 'Do A'),
            new Solution(title: 'Fix B', description: 'Do B'),
        ];
        $decoded = $this->renderAndDecode($this->makeContext(isDevelopment: true, solutions: $solutions));

        self::assertCount(2, $decoded['solutions']);
        self::assertSame('Fix A', $decoded['solutions'][0]['title']);
        self::assertSame('Fix B', $decoded['solutions'][1]['title']);
    }

    #[Test]
    public function traceFrameWithNullClassAndFunction(): void
    {
        $frames = [
            new Frame(
                file: '/app/index.php',
                line: 1,
                class: null,
                function: null,
                codeSnippet: [],
                isApplication: true,
            ),
        ];
        $decoded = $this->renderAndDecode($this->makeContext(isDevelopment: true, frames: $frames));

        self::assertNull($decoded['exception']['trace'][0]['class']);
        self::assertNull($decoded['exception']['trace'][0]['function']);
    }

    /**
     * @param list<Frame> $frames
     * @param list<Solution> $solutions
     */
    private function makeContext(
        string $message = 'Test error',
        int $statusCode = 500,
        bool $isDevelopment = true,
        array $frames = [],
        array $solutions = [],
    ): ErrorContext {
        return new ErrorContext(
            exception: new \RuntimeException($message),
            stackTrace: new StackTrace(frames: $frames),
            statusCode: $statusCode,
            request: null,
            environment: [],
            context: [],
            solutions: $solutions,
            isDevelopment: $isDevelopment,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function renderAndDecode(ErrorContext $context): array
    {
        $renderer = new JsonRenderer();
        $output = $renderer->render($context);

        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
