<?php

declare(strict_types=1);

namespace PHPdot\ErrorHandler\Tests\Integration;

use PHPdot\Http\Message\ServerRequest;
use PHPdot\ErrorHandler\Context\ErrorContext;
use PHPdot\ErrorHandler\Contract\ContextProviderInterface;
use PHPdot\ErrorHandler\Contract\RendererInterface;
use PHPdot\ErrorHandler\Contract\SolutionProviderInterface;
use PHPdot\ErrorHandler\ExceptionHandler;
use PHPdot\ErrorHandler\Renderer\HtmlDevRenderer;
use PHPdot\ErrorHandler\Renderer\HtmlProdRenderer;
use PHPdot\ErrorHandler\Renderer\JsonRenderer;
use PHPdot\ErrorHandler\Renderer\PlainTextRenderer;
use PHPdot\ErrorHandler\Solution\Solution;
use PHPdot\ErrorHandler\Solution\SolutionLink;
use Psr\Http\Message\ServerRequestInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FullFlowTest extends TestCase
{
    #[Test]
    public function devHtmlOutputContainsExceptionClass(): void
    {
        $handler = $this->makeDevHandler();

        $output = $handler->handle(new \RuntimeException('Test error'));

        self::assertStringContainsString('RuntimeException', $output);
    }

    #[Test]
    public function devHtmlOutputContainsExceptionMessage(): void
    {
        $handler = $this->makeDevHandler();

        $output = $handler->handle(new \RuntimeException('Something went very wrong'));

        self::assertStringContainsString('Something went very wrong', $output);
    }

    #[Test]
    public function devHtmlOutputContainsStatusCode(): void
    {
        $handler = $this->makeDevHandler();

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertStringContainsString('500', $output);
    }

    #[Test]
    public function devHtmlOutputContainsFileAndLine(): void
    {
        $handler = $this->makeDevHandler();
        $exception = new \RuntimeException('test');

        $output = $handler->handle($exception);

        self::assertStringContainsString($exception->getFile(), $output);
        self::assertStringContainsString((string) $exception->getLine(), $output);
    }

    #[Test]
    public function devHtmlOutputContainsStackTraceSection(): void
    {
        $handler = $this->makeDevHandler();

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertStringContainsString('Stack Trace', $output);
    }

    #[Test]
    public function devHtmlOutputIsValidHtml(): void
    {
        $handler = $this->makeDevHandler();

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertStringContainsString('<!DOCTYPE html>', $output);
        self::assertStringContainsString('</html>', $output);
    }

    #[Test]
    public function prodHtmlOutputHidesExceptionClass(): void
    {
        $handler = $this->makeProdHandler();

        $output = $handler->handle(new \RuntimeException('Internal details'));

        self::assertStringNotContainsString('RuntimeException', $output);
    }

    #[Test]
    public function prodHtmlOutputHidesExceptionMessage(): void
    {
        $handler = $this->makeProdHandler();

        $output = $handler->handle(new \RuntimeException('Secret internal error details'));

        self::assertStringNotContainsString('Secret internal error details', $output);
    }

    #[Test]
    public function prodHtmlOutputHidesStackTrace(): void
    {
        $handler = $this->makeProdHandler();

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertStringNotContainsString('Stack Trace', $output);
        self::assertStringNotContainsString('frame-code', $output);
        self::assertStringNotContainsString('frame-header', $output);
    }

    #[Test]
    public function prodHtmlOutputShowsStatusCode(): void
    {
        $handler = $this->makeProdHandler();

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertStringContainsString('500', $output);
    }

    #[Test]
    public function prodHtmlOutputShowsFriendlyMessage(): void
    {
        $handler = $this->makeProdHandler();

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertStringContainsString('Server Error', $output);
    }

    #[Test]
    public function prodHtmlOutputIsValidHtml(): void
    {
        $handler = $this->makeProdHandler();

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertStringContainsString('<!DOCTYPE html>', $output);
        self::assertStringContainsString('</html>', $output);
    }

    #[Test]
    public function jsonOutputIsValidRfc9457(): void
    {
        $handler = $this->makeDevHandler();
        $request = new ServerRequest('GET', '/api', ['Accept' => 'application/json']);

        $output = $handler->handle(new \RuntimeException('API error'), $request);
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        // RFC 9457 required fields
        self::assertArrayHasKey('type', $decoded);
        self::assertArrayHasKey('title', $decoded);
        self::assertArrayHasKey('status', $decoded);
        self::assertArrayHasKey('detail', $decoded);
    }

    #[Test]
    public function jsonOutputProdHidesInternals(): void
    {
        $handler = $this->makeProdHandler();
        $request = new ServerRequest('GET', '/api', ['Accept' => 'application/json']);

        $output = $handler->handle(new \RuntimeException('Secret info'), $request);
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('exception', $decoded);
        self::assertStringNotContainsString('Secret info', $decoded['detail']);
    }

    #[Test]
    public function jsonOutputDevIncludesExceptionDetails(): void
    {
        $handler = $this->makeDevHandler();
        $request = new ServerRequest('GET', '/api', ['Accept' => 'application/json']);

        $output = $handler->handle(new \RuntimeException('Detailed error'), $request);
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('exception', $decoded);
        self::assertSame('RuntimeException', $decoded['exception']['class']);
        self::assertSame('Detailed error', $decoded['exception']['message']);
    }

    #[Test]
    public function contextTabsAppearInDevHtmlOutput(): void
    {
        $handler = $this->makeDevHandler();

        $provider = new class implements ContextProviderInterface {
            public function getLabel(): string { return 'Database'; }
            public function collect(\Throwable $exception, ?ServerRequestInterface $request): array
            {
                return ['queries' => '5', 'total_time' => '12ms'];
            }
        };

        $handler->addContextProvider($provider);

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertStringContainsString('Database', $output);
        self::assertStringContainsString('queries', $output);
    }

    #[Test]
    public function solutionsAppearInDevHtmlOutput(): void
    {
        $handler = $this->makeDevHandler();

        $provider = new class implements SolutionProviderInterface {
            public function canSolve(\Throwable $exception): bool { return true; }
            public function getSolutions(\Throwable $exception): array
            {
                return [
                    new Solution(
                        title: 'Run composer install',
                        description: 'Your autoload files may be outdated',
                        links: [new SolutionLink(label: 'Composer docs', url: 'https://getcomposer.org')],
                    ),
                ];
            }
        };

        $handler->addSolutionProvider($provider);

        $output = $handler->handle(new \RuntimeException('Class not found'));

        self::assertStringContainsString('Run composer install', $output);
        self::assertStringContainsString('Your autoload files may be outdated', $output);
        self::assertStringContainsString('Composer docs', $output);
        self::assertStringContainsString('https://getcomposer.org', $output);
    }

    #[Test]
    public function solutionsAppearInJsonDevOutput(): void
    {
        $handler = $this->makeDevHandler();

        $provider = new class implements SolutionProviderInterface {
            public function canSolve(\Throwable $exception): bool { return true; }
            public function getSolutions(\Throwable $exception): array
            {
                return [new Solution(title: 'Fix it', description: 'Like this')];
            }
        };

        $handler->addSolutionProvider($provider);
        $request = new ServerRequest('GET', '/api', ['Accept' => 'application/json']);

        $output = $handler->handle(new \RuntimeException('test'), $request);
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('solutions', $decoded);
        self::assertSame('Fix it', $decoded['solutions'][0]['title']);
    }

    #[Test]
    public function solutionsAppearInPlainTextDevOutput(): void
    {
        $handler = new ExceptionHandler(
            environment: 'development',
            devRenderer: new PlainTextRenderer(),
            prodRenderer: new PlainTextRenderer(),
            jsonRenderer: new JsonRenderer(),
        );

        $provider = new class implements SolutionProviderInterface {
            public function canSolve(\Throwable $exception): bool { return true; }
            public function getSolutions(\Throwable $exception): array
            {
                return [new Solution(title: 'Fix it', description: 'Like this')];
            }
        };

        $handler->addSolutionProvider($provider);
        $output = $handler->handle(new \RuntimeException('test'));

        self::assertStringContainsString('Suggested solutions:', $output);
        self::assertStringContainsString('Fix it: Like this', $output);
    }

    #[Test]
    public function customRendererIsUsed(): void
    {
        $customRenderer = new class implements RendererInterface {
            public function render(ErrorContext $context): string
            {
                return 'CUSTOM:' . $context->exception->getMessage();
            }
        };

        $handler = new ExceptionHandler(
            environment: 'development',
            devRenderer: $customRenderer,
            prodRenderer: new HtmlProdRenderer(),
            jsonRenderer: new JsonRenderer(),
        );

        $output = $handler->handle(new \RuntimeException('hello'));

        self::assertSame('CUSTOM:hello', $output);
    }

    #[Test]
    public function customProdRendererIsUsed(): void
    {
        $customRenderer = new class implements RendererInterface {
            public function render(ErrorContext $context): string
            {
                return 'CUSTOM_PROD:' . $context->statusCode;
            }
        };

        $handler = new ExceptionHandler(
            environment: 'production',
            devRenderer: new HtmlDevRenderer(),
            prodRenderer: $customRenderer,
            jsonRenderer: new JsonRenderer(),
        );

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertSame('CUSTOM_PROD:500', $output);
    }

    #[Test]
    public function customJsonRendererIsUsed(): void
    {
        $customRenderer = new class implements RendererInterface {
            public function render(ErrorContext $context): string
            {
                return '{"custom":true}';
            }
        };

        $handler = new ExceptionHandler(
            environment: 'production',
            devRenderer: new HtmlDevRenderer(),
            prodRenderer: new HtmlProdRenderer(),
            jsonRenderer: $customRenderer,
        );

        $request = new ServerRequest('GET', '/', ['Accept' => 'application/json']);
        $output = $handler->handle(new \RuntimeException('test'), $request);

        self::assertSame('{"custom":true}', $output);
    }

    #[Test]
    public function statusCodeMappingInvalidArgument(): void
    {
        $handler = $this->makeDevHandler();
        $request = new ServerRequest('GET', '/api', ['Accept' => 'application/json']);

        $output = $handler->handle(new \InvalidArgumentException('bad input'), $request);
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(400, $decoded['status']);
        self::assertSame('Bad Request', $decoded['title']);
    }

    #[Test]
    public function statusCodeMappingDomainException(): void
    {
        $handler = $this->makeDevHandler();
        $request = new ServerRequest('GET', '/api', ['Accept' => 'application/json']);

        $output = $handler->handle(new \DomainException('invalid'), $request);
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $decoded['status']);
        self::assertSame('Unprocessable Entity', $decoded['title']);
    }

    #[Test]
    public function statusCodeMappingCustomGetStatusCode(): void
    {
        $handler = $this->makeDevHandler();
        $request = new ServerRequest('GET', '/api', ['Accept' => 'application/json']);

        $exception = new class ('not found') extends \RuntimeException {
            public function getStatusCode(): int { return 404; }
        };

        $output = $handler->handle($exception, $request);
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(404, $decoded['status']);
        self::assertSame('Not Found', $decoded['title']);
    }

    #[Test]
    public function devHtmlOutputEscapesHtml(): void
    {
        $handler = $this->makeDevHandler();

        $output = $handler->handle(new \RuntimeException('<script>alert("xss")</script>'));

        // The message should be escaped
        self::assertStringNotContainsString('<script>alert("xss")</script>', $output);
        self::assertStringContainsString('&lt;script&gt;', $output);
    }

    #[Test]
    public function devHtmlOutputContainsEnvironmentTab(): void
    {
        $handler = $this->makeDevHandler();

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertStringContainsString('Environment', $output);
    }

    #[Test]
    public function devHtmlOutputContainsRequestTabWhenRequestProvided(): void
    {
        $handler = $this->makeDevHandler();
        $request = new ServerRequest('POST', '/users', ['Content-Type' => 'application/json']);

        $output = $handler->handle(new \RuntimeException('test'), $request);

        self::assertStringContainsString('Request', $output);
        self::assertStringContainsString('POST', $output);
        self::assertStringContainsString('/users', $output);
    }

    #[Test]
    public function prodHtmlFor404ShowsNotFound(): void
    {
        $handler = $this->makeProdHandler();

        $exception = new class ('not found') extends \RuntimeException {
            public function getStatusCode(): int { return 404; }
        };

        $output = $handler->handle($exception);

        self::assertStringContainsString('404', $output);
        self::assertStringContainsString('Page Not Found', $output);
    }

    #[Test]
    public function prodHtmlFor403ShowsForbidden(): void
    {
        $handler = $this->makeProdHandler();

        $exception = new class ('forbidden') extends \RuntimeException {
            public function getStatusCode(): int { return 403; }
        };

        $output = $handler->handle($exception);

        self::assertStringContainsString('403', $output);
        self::assertStringContainsString('Forbidden', $output);
    }

    #[Test]
    public function endToEndWithMultipleProviders(): void
    {
        $handler = $this->makeDevHandler();

        // Add two context providers
        $handler->addContextProvider(new class implements ContextProviderInterface {
            public function getLabel(): string { return 'Cache'; }
            public function collect(\Throwable $e, ?ServerRequestInterface $r): array
            {
                return ['hits' => '100', 'misses' => '5'];
            }
        });

        $handler->addContextProvider(new class implements ContextProviderInterface {
            public function getLabel(): string { return 'Queue'; }
            public function collect(\Throwable $e, ?ServerRequestInterface $r): array
            {
                return ['pending' => '42'];
            }
        });

        // Add solution provider
        $handler->addSolutionProvider(new class implements SolutionProviderInterface {
            public function canSolve(\Throwable $e): bool { return true; }
            public function getSolutions(\Throwable $e): array
            {
                return [new Solution(title: 'Clear cache', description: 'Run cache:clear')];
            }
        });

        $output = $handler->handle(new \RuntimeException('Cache error'));

        self::assertStringContainsString('Cache', $output);
        self::assertStringContainsString('Queue', $output);
        self::assertStringContainsString('Clear cache', $output);
        self::assertStringContainsString('Cache error', $output);
    }

    #[Test]
    public function endToEndJsonWithSolutionAndRequest(): void
    {
        $handler = $this->makeDevHandler();

        $handler->addSolutionProvider(new class implements SolutionProviderInterface {
            public function canSolve(\Throwable $e): bool { return true; }
            public function getSolutions(\Throwable $e): array
            {
                return [new Solution(title: 'Retry', description: 'Try again later')];
            }
        });

        $request = new ServerRequest('POST', '/api/users', ['Accept' => 'application/json']);
        $output = $handler->handle(new \DomainException('Validation failed'), $request);
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $decoded['status']);
        self::assertSame('Unprocessable Entity', $decoded['title']);
        self::assertSame('Validation failed', $decoded['detail']);
        self::assertArrayHasKey('solutions', $decoded);
        self::assertSame('Retry', $decoded['solutions'][0]['title']);
    }

    #[Test]
    public function plainTextRendererEndToEnd(): void
    {
        $handler = new ExceptionHandler(
            environment: 'development',
            devRenderer: new PlainTextRenderer(),
            prodRenderer: new PlainTextRenderer(),
            jsonRenderer: new JsonRenderer(),
        );

        $output = $handler->handle(new \RuntimeException('CLI error'));

        self::assertStringContainsString('[RuntimeException]', $output);
        self::assertStringContainsString('CLI error', $output);
        self::assertStringContainsString('Stack trace:', $output);
    }

    #[Test]
    public function plainTextProdModeHidesTrace(): void
    {
        $handler = new ExceptionHandler(
            environment: 'production',
            devRenderer: new PlainTextRenderer(),
            prodRenderer: new PlainTextRenderer(),
            jsonRenderer: new JsonRenderer(),
        );

        $output = $handler->handle(new \RuntimeException('Prod CLI error'));

        self::assertStringContainsString('[RuntimeException]', $output);
        self::assertStringContainsString('Prod CLI error', $output);
        self::assertStringNotContainsString('Stack trace:', $output);
    }

    #[Test]
    public function brokenContextProviderDoesNotAffectOutput(): void
    {
        $handler = $this->makeDevHandler();

        $handler->addContextProvider(new class implements ContextProviderInterface {
            public function getLabel(): string { return 'Broken'; }
            public function collect(\Throwable $e, ?ServerRequestInterface $r): array
            {
                throw new \RuntimeException('Provider is broken');
            }
        });

        // Should still render successfully without the provider's exception becoming the main error
        $output = $handler->handle(new \RuntimeException('original test error'));

        self::assertStringContainsString('RuntimeException', $output);
        self::assertStringContainsString('original test error', $output);
    }

    #[Test]
    public function brokenSolutionProviderDoesNotAffectOutput(): void
    {
        $handler = $this->makeDevHandler();

        $handler->addSolutionProvider(new class implements SolutionProviderInterface {
            public function canSolve(\Throwable $e): bool { return true; }
            public function getSolutions(\Throwable $e): array
            {
                throw new \LogicException('Solution provider crashed');
            }
        });

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertStringContainsString('RuntimeException', $output);
    }

    #[Test]
    public function devHtmlCodeSnippetIsPresent(): void
    {
        $handler = $this->makeDevHandler();

        $output = $handler->handle(new \RuntimeException('test'));

        // The first frame should have code from this very file
        self::assertStringContainsString('frame-code', $output);
    }

    #[Test]
    public function requestTabShowsHeaders(): void
    {
        $handler = $this->makeDevHandler();
        $request = new ServerRequest('GET', '/test', [
            'Authorization' => 'Bearer token123',
            'X-Custom-Header' => 'custom-value',
        ]);

        $output = $handler->handle(new \RuntimeException('test'), $request);

        self::assertStringContainsString('Authorization', $output);
        self::assertStringContainsString('X-Custom-Header', $output);
    }

    private function makeDevHandler(): ExceptionHandler
    {
        return new ExceptionHandler(
            environment: 'development',
            devRenderer: new HtmlDevRenderer(),
            prodRenderer: new HtmlProdRenderer(),
            jsonRenderer: new JsonRenderer(),
        );
    }

    private function makeProdHandler(): ExceptionHandler
    {
        return new ExceptionHandler(
            environment: 'production',
            devRenderer: new HtmlDevRenderer(),
            prodRenderer: new HtmlProdRenderer(),
            jsonRenderer: new JsonRenderer(),
        );
    }
}
