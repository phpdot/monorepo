<?php

declare(strict_types=1);

namespace PHPdot\ErrorHandler\Tests\Unit;

use PHPdot\Http\Message\ServerRequest;
use PHPdot\ErrorHandler\Context\ContextTab;
use PHPdot\ErrorHandler\Context\ErrorContext;
use PHPdot\ErrorHandler\Contract\ContextProviderInterface;
use PHPdot\ErrorHandler\Contract\RendererInterface;
use PHPdot\ErrorHandler\Contract\SolutionProviderInterface;
use PHPdot\ErrorHandler\ExceptionHandler;
use PHPdot\ErrorHandler\Renderer\HtmlDevRenderer;
use PHPdot\ErrorHandler\Renderer\HtmlProdRenderer;
use PHPdot\ErrorHandler\Renderer\JsonRenderer;
use PHPdot\ErrorHandler\Solution\Solution;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExceptionHandlerTest extends TestCase
{
    #[Test]
    public function handleReturnsRenderedOutput(): void
    {
        $handler = $this->makeHandler(environment: 'development');

        $output = $handler->handle(new \RuntimeException('Something broke'));

        self::assertNotEmpty($output);
    }

    #[Test]
    public function handleUsesDevRendererInDevelopment(): void
    {
        $devRenderer = $this->createRendererReturning('DEV_OUTPUT');
        $prodRenderer = $this->createRendererReturning('PROD_OUTPUT');

        $handler = new ExceptionHandler(
            environment: 'development',
            devRenderer: $devRenderer,
            prodRenderer: $prodRenderer,
            jsonRenderer: new JsonRenderer(),
        );

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertSame('DEV_OUTPUT', $output);
    }

    #[Test]
    public function handleUsesProdRendererInProduction(): void
    {
        $devRenderer = $this->createRendererReturning('DEV_OUTPUT');
        $prodRenderer = $this->createRendererReturning('PROD_OUTPUT');

        $handler = new ExceptionHandler(
            environment: 'production',
            devRenderer: $devRenderer,
            prodRenderer: $prodRenderer,
            jsonRenderer: new JsonRenderer(),
        );

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertSame('PROD_OUTPUT', $output);
    }

    #[Test]
    public function handleUsesJsonRendererWhenAcceptJson(): void
    {
        $jsonRenderer = $this->createRendererReturning('JSON_OUTPUT');

        $handler = new ExceptionHandler(
            environment: 'development',
            devRenderer: $this->createRendererReturning('DEV'),
            prodRenderer: $this->createRendererReturning('PROD'),
            jsonRenderer: $jsonRenderer,
        );

        $request = new ServerRequest('GET', '/test', ['Accept' => 'application/json']);
        $output = $handler->handle(new \RuntimeException('test'), $request);

        self::assertSame('JSON_OUTPUT', $output);
    }

    #[Test]
    public function handleUsesJsonRendererWhenAcceptProblemJson(): void
    {
        $jsonRenderer = $this->createRendererReturning('JSON_OUTPUT');

        $handler = new ExceptionHandler(
            environment: 'production',
            devRenderer: $this->createRendererReturning('DEV'),
            prodRenderer: $this->createRendererReturning('PROD'),
            jsonRenderer: $jsonRenderer,
        );

        $request = new ServerRequest('GET', '/api', ['Accept' => 'application/problem+json']);
        $output = $handler->handle(new \RuntimeException('test'), $request);

        self::assertSame('JSON_OUTPUT', $output);
    }

    #[Test]
    public function handleUsesHtmlRendererWhenAcceptHtml(): void
    {
        $handler = new ExceptionHandler(
            environment: 'development',
            devRenderer: $this->createRendererReturning('DEV'),
            prodRenderer: $this->createRendererReturning('PROD'),
            jsonRenderer: $this->createRendererReturning('JSON'),
        );

        $request = new ServerRequest('GET', '/test', ['Accept' => 'text/html']);
        $output = $handler->handle(new \RuntimeException('test'), $request);

        self::assertSame('DEV', $output);
    }

    #[Test]
    public function handleUsesHtmlRendererWhenNoRequest(): void
    {
        $handler = new ExceptionHandler(
            environment: 'development',
            devRenderer: $this->createRendererReturning('DEV'),
            prodRenderer: $this->createRendererReturning('PROD'),
            jsonRenderer: $this->createRendererReturning('JSON'),
        );

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertSame('DEV', $output);
    }

    #[Test]
    public function getStatusCodeReturns500ForRuntimeException(): void
    {
        $handler = $this->makeHandler();

        self::assertSame(500, $handler->getStatusCode(new \RuntimeException('test')));
    }

    #[Test]
    public function getStatusCodeReturns400ForInvalidArgumentException(): void
    {
        $handler = $this->makeHandler();

        self::assertSame(400, $handler->getStatusCode(new \InvalidArgumentException('bad')));
    }

    #[Test]
    public function getStatusCodeReturns422ForDomainException(): void
    {
        $handler = $this->makeHandler();

        self::assertSame(422, $handler->getStatusCode(new \DomainException('invalid')));
    }

    #[Test]
    public function getStatusCodeReturns500ForLogicException(): void
    {
        $handler = $this->makeHandler();

        self::assertSame(500, $handler->getStatusCode(new \LogicException('logic')));
    }

    #[Test]
    public function getStatusCodeReturns500ForTypeError(): void
    {
        $handler = $this->makeHandler();

        self::assertSame(500, $handler->getStatusCode(new \TypeError('type')));
    }

    #[Test]
    public function getStatusCodeReturns500ForGenericException(): void
    {
        $handler = $this->makeHandler();

        self::assertSame(500, $handler->getStatusCode(new \Exception('generic')));
    }

    #[Test]
    public function getStatusCodeUsesGetStatusCodeMethod(): void
    {
        $handler = $this->makeHandler();

        $exception = new class ('not found') extends \RuntimeException {
            public function getStatusCode(): int
            {
                return 404;
            }
        };

        self::assertSame(404, $handler->getStatusCode($exception));
    }

    #[Test]
    public function getStatusCodeIgnoresInvalidStatusCodeFromMethod(): void
    {
        $handler = $this->makeHandler();

        $exception = new class ('bad') extends \RuntimeException {
            public function getStatusCode(): int
            {
                return 999;
            }
        };

        // 999 is out of range (100-599), falls back to match
        self::assertSame(500, $handler->getStatusCode($exception));
    }

    #[Test]
    public function getStatusCodeIgnoresStatusCodeBelow100(): void
    {
        $handler = $this->makeHandler();

        $exception = new class ('bad') extends \RuntimeException {
            public function getStatusCode(): int
            {
                return 50;
            }
        };

        self::assertSame(500, $handler->getStatusCode($exception));
    }

    #[Test]
    public function getStatusCodeAcceptsStatusCode100(): void
    {
        $handler = $this->makeHandler();

        $exception = new class ('continue') extends \RuntimeException {
            public function getStatusCode(): int
            {
                return 100;
            }
        };

        self::assertSame(100, $handler->getStatusCode($exception));
    }

    #[Test]
    public function getStatusCodeAcceptsStatusCode599(): void
    {
        $handler = $this->makeHandler();

        $exception = new class ('edge') extends \RuntimeException {
            public function getStatusCode(): int
            {
                return 599;
            }
        };

        self::assertSame(599, $handler->getStatusCode($exception));
    }

    #[Test]
    public function getEnvironmentReturnsDevelopment(): void
    {
        $handler = $this->makeHandler(environment: 'development');

        self::assertSame('development', $handler->getEnvironment());
    }

    #[Test]
    public function getEnvironmentReturnsProduction(): void
    {
        $handler = $this->makeHandler(environment: 'production');

        self::assertSame('production', $handler->getEnvironment());
    }

    #[Test]
    public function sensitiveKeysAreMasked(): void
    {
        $handler = $this->makeHandler(environment: 'development');

        // Set $_SERVER values for testing
        $original = $_SERVER;
        $_SERVER['APP_PASSWORD'] = 'secret123';
        $_SERVER['APP_NAME'] = 'TestApp';
        $_SERVER['API_KEY'] = 'key123';
        $_SERVER['SAFE_VALUE'] = 'visible';

        try {
            $capturedContext = null;
            $renderer = new class ($capturedContext) implements RendererInterface {
                /** @param ErrorContext|null &$ref */
                public function __construct(private mixed &$ref) {}
                public function render(ErrorContext $context): string
                {
                    $this->ref = $context;
                    return '';
                }
            };
            $handler->setDevRenderer($renderer);
            $handler->handle(new \RuntimeException('test'));

            self::assertNotNull($capturedContext);
            self::assertSame('********', $capturedContext->environment['APP_PASSWORD']);
            self::assertSame('********', $capturedContext->environment['API_KEY']);
            self::assertSame('TestApp', $capturedContext->environment['APP_NAME']);
            self::assertSame('visible', $capturedContext->environment['SAFE_VALUE']);
        } finally {
            $_SERVER = $original;
        }
    }

    #[Test]
    public function customSensitiveKeysAreMasked(): void
    {
        $handler = $this->makeHandler(environment: 'development');
        $handler->setSensitiveKeys(['CUSTOM_SECRET']);

        $original = $_SERVER;
        $_SERVER['CUSTOM_SECRET'] = 'hidden';
        $_SERVER['PASSWORD'] = 'nowvisible'; // not in custom list

        try {
            $capturedContext = null;
            $renderer = new class ($capturedContext) implements RendererInterface {
                /** @param ErrorContext|null &$ref */
                public function __construct(private mixed &$ref) {}
                public function render(ErrorContext $context): string
                {
                    $this->ref = $context;
                    return '';
                }
            };
            $handler->setDevRenderer($renderer);
            $handler->handle(new \RuntimeException('test'));

            self::assertNotNull($capturedContext);
            self::assertSame('********', $capturedContext->environment['CUSTOM_SECRET']);
            // PASSWORD is not in the custom list, so it should be visible
            self::assertSame('nowvisible', $capturedContext->environment['PASSWORD']);
        } finally {
            $_SERVER = $original;
        }
    }

    #[Test]
    public function contextProvidersAreCalled(): void
    {
        $handler = $this->makeHandler(environment: 'development');

        $provider = new class implements ContextProviderInterface {
            public function getLabel(): string { return 'TestTab'; }
            public function collect(\Throwable $exception, ?ServerRequestInterface $request): array
            {
                return ['key' => 'value'];
            }
        };

        $handler->addContextProvider($provider);

        $capturedContext = null;
        $renderer = new class ($capturedContext) implements RendererInterface {
            /** @param ErrorContext|null &$ref */
            public function __construct(private mixed &$ref) {}
            public function render(ErrorContext $context): string
            {
                $this->ref = $context;
                return '';
            }
        };
        $handler->setDevRenderer($renderer);
        $handler->handle(new \RuntimeException('test'));

        self::assertNotNull($capturedContext);
        self::assertCount(1, $capturedContext->context);
        self::assertSame('TestTab', $capturedContext->context[0]->label);
        self::assertSame(['key' => 'value'], $capturedContext->context[0]->data);
    }

    #[Test]
    public function contextProviderReturningEmptyArrayIsSkipped(): void
    {
        $handler = $this->makeHandler(environment: 'development');

        $provider = new class implements ContextProviderInterface {
            public function getLabel(): string { return 'Empty'; }
            public function collect(\Throwable $exception, ?ServerRequestInterface $request): array
            {
                return [];
            }
        };

        $handler->addContextProvider($provider);

        $capturedContext = null;
        $renderer = new class ($capturedContext) implements RendererInterface {
            /** @param ErrorContext|null &$ref */
            public function __construct(private mixed &$ref) {}
            public function render(ErrorContext $context): string
            {
                $this->ref = $context;
                return '';
            }
        };
        $handler->setDevRenderer($renderer);
        $handler->handle(new \RuntimeException('test'));

        self::assertNotNull($capturedContext);
        self::assertCount(0, $capturedContext->context);
    }

    #[Test]
    public function disabledContextProviderDoesNotCrash(): void
    {
        $handler = $this->makeHandler(environment: 'development');

        $provider = new class implements ContextProviderInterface {
            public function getLabel(): string { return 'Broken'; }
            public function collect(\Throwable $exception, ?ServerRequestInterface $request): array
            {
                throw new \RuntimeException('Provider crashed');
            }
        };

        $handler->addContextProvider($provider);

        // Should not throw
        $output = $handler->handle(new \RuntimeException('test'));

        self::assertNotEmpty($output);
    }

    #[Test]
    public function solutionProvidersAreCalled(): void
    {
        $handler = $this->makeHandler(environment: 'development');

        $provider = new class implements SolutionProviderInterface {
            public function canSolve(\Throwable $exception): bool { return true; }
            public function getSolutions(\Throwable $exception): array
            {
                return [new Solution(title: 'Fix this', description: 'Do that')];
            }
        };

        $handler->addSolutionProvider($provider);

        $capturedContext = null;
        $renderer = new class ($capturedContext) implements RendererInterface {
            /** @param ErrorContext|null &$ref */
            public function __construct(private mixed &$ref) {}
            public function render(ErrorContext $context): string
            {
                $this->ref = $context;
                return '';
            }
        };
        $handler->setDevRenderer($renderer);
        $handler->handle(new \RuntimeException('test'));

        self::assertNotNull($capturedContext);
        self::assertCount(1, $capturedContext->solutions);
        self::assertSame('Fix this', $capturedContext->solutions[0]->title);
    }

    #[Test]
    public function solutionProviderThatCannotSolveIsSkipped(): void
    {
        $handler = $this->makeHandler(environment: 'development');

        $provider = new class implements SolutionProviderInterface {
            public function canSolve(\Throwable $exception): bool { return false; }
            public function getSolutions(\Throwable $exception): array
            {
                return [new Solution(title: 'Unreachable', description: 'Never')];
            }
        };

        $handler->addSolutionProvider($provider);

        $capturedContext = null;
        $renderer = new class ($capturedContext) implements RendererInterface {
            /** @param ErrorContext|null &$ref */
            public function __construct(private mixed &$ref) {}
            public function render(ErrorContext $context): string
            {
                $this->ref = $context;
                return '';
            }
        };
        $handler->setDevRenderer($renderer);
        $handler->handle(new \RuntimeException('test'));

        self::assertNotNull($capturedContext);
        self::assertCount(0, $capturedContext->solutions);
    }

    #[Test]
    public function brokenSolutionProviderDoesNotCrash(): void
    {
        $handler = $this->makeHandler(environment: 'development');

        $provider = new class implements SolutionProviderInterface {
            public function canSolve(\Throwable $exception): bool { throw new \RuntimeException('boom'); }
            public function getSolutions(\Throwable $exception): array { return []; }
        };

        $handler->addSolutionProvider($provider);

        $output = $handler->handle(new \RuntimeException('test'));
        self::assertNotEmpty($output);
    }

    #[Test]
    public function logsWithErrorLevelFor500(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('log')
            ->with(
                LogLevel::ERROR,
                'Server error',
                self::callback(static fn (array $ctx): bool =>
                    $ctx['status_code'] === 500 && $ctx['exception'] instanceof \Throwable
                ),
            );

        $handler = $this->makeHandler();
        $handler->setLogger($logger);
        $handler->handle(new \RuntimeException('Server error'));
    }

    #[Test]
    public function logsWithWarningLevelFor400(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('log')
            ->with(
                LogLevel::WARNING,
                'Bad input',
                self::anything(),
            );

        $handler = $this->makeHandler();
        $handler->setLogger($logger);
        $handler->handle(new \InvalidArgumentException('Bad input'));
    }

    #[Test]
    public function logsWithWarningLevelFor422(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('log')
            ->with(
                LogLevel::WARNING,
                'Invalid domain',
                self::anything(),
            );

        $handler = $this->makeHandler();
        $handler->setLogger($logger);
        $handler->handle(new \DomainException('Invalid domain'));
    }

    #[Test]
    public function doesNotLogWhenNoLogger(): void
    {
        $handler = $this->makeHandler();

        // Should not throw
        $output = $handler->handle(new \RuntimeException('test'));

        self::assertNotEmpty($output);
    }

    #[Test]
    public function setDevRendererReplacesRenderer(): void
    {
        $handler = $this->makeHandler(environment: 'development');
        $handler->setDevRenderer($this->createRendererReturning('CUSTOM_DEV'));

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertSame('CUSTOM_DEV', $output);
    }

    #[Test]
    public function setProdRendererReplacesRenderer(): void
    {
        $handler = $this->makeHandler(environment: 'production');
        $handler->setProdRenderer($this->createRendererReturning('CUSTOM_PROD'));

        $output = $handler->handle(new \RuntimeException('test'));

        self::assertSame('CUSTOM_PROD', $output);
    }

    #[Test]
    public function setJsonRendererReplacesRenderer(): void
    {
        $handler = $this->makeHandler(environment: 'production');
        $handler->setJsonRenderer($this->createRendererReturning('CUSTOM_JSON'));

        $request = new ServerRequest('GET', '/api', ['Accept' => 'application/json']);
        $output = $handler->handle(new \RuntimeException('test'), $request);

        self::assertSame('CUSTOM_JSON', $output);
    }

    #[Test]
    public function multipleSolutionProvidersAggregated(): void
    {
        $handler = $this->makeHandler(environment: 'development');

        $provider1 = new class implements SolutionProviderInterface {
            public function canSolve(\Throwable $exception): bool { return true; }
            public function getSolutions(\Throwable $exception): array
            {
                return [new Solution(title: 'Solution 1', description: 'First')];
            }
        };

        $provider2 = new class implements SolutionProviderInterface {
            public function canSolve(\Throwable $exception): bool { return true; }
            public function getSolutions(\Throwable $exception): array
            {
                return [
                    new Solution(title: 'Solution 2', description: 'Second'),
                    new Solution(title: 'Solution 3', description: 'Third'),
                ];
            }
        };

        $handler->addSolutionProvider($provider1);
        $handler->addSolutionProvider($provider2);

        $capturedContext = null;
        $renderer = new class ($capturedContext) implements RendererInterface {
            /** @param ErrorContext|null &$ref */
            public function __construct(private mixed &$ref) {}
            public function render(ErrorContext $context): string
            {
                $this->ref = $context;
                return '';
            }
        };
        $handler->setDevRenderer($renderer);
        $handler->handle(new \RuntimeException('test'));

        self::assertNotNull($capturedContext);
        self::assertCount(3, $capturedContext->solutions);
    }

    #[Test]
    public function multipleContextProviders(): void
    {
        $handler = $this->makeHandler(environment: 'development');

        $provider1 = new class implements ContextProviderInterface {
            public function getLabel(): string { return 'Tab1'; }
            public function collect(\Throwable $e, ?ServerRequestInterface $r): array { return ['a' => 1]; }
        };

        $provider2 = new class implements ContextProviderInterface {
            public function getLabel(): string { return 'Tab2'; }
            public function collect(\Throwable $e, ?ServerRequestInterface $r): array { return ['b' => 2]; }
        };

        $handler->addContextProvider($provider1);
        $handler->addContextProvider($provider2);

        $capturedContext = null;
        $renderer = new class ($capturedContext) implements RendererInterface {
            /** @param ErrorContext|null &$ref */
            public function __construct(private mixed &$ref) {}
            public function render(ErrorContext $context): string
            {
                $this->ref = $context;
                return '';
            }
        };
        $handler->setDevRenderer($renderer);
        $handler->handle(new \RuntimeException('test'));

        self::assertNotNull($capturedContext);
        self::assertCount(2, $capturedContext->context);
    }

    #[Test]
    public function jsonTakesPriorityOverEnvironment(): void
    {
        $handler = new ExceptionHandler(
            environment: 'production',
            devRenderer: $this->createRendererReturning('DEV'),
            prodRenderer: $this->createRendererReturning('PROD'),
            jsonRenderer: $this->createRendererReturning('JSON'),
        );

        $request = new ServerRequest('GET', '/', ['Accept' => 'application/json']);
        $output = $handler->handle(new \RuntimeException('test'), $request);

        self::assertSame('JSON', $output);
    }

    #[Test]
    public function jsonTakesPriorityInDevelopment(): void
    {
        $handler = new ExceptionHandler(
            environment: 'development',
            devRenderer: $this->createRendererReturning('DEV'),
            prodRenderer: $this->createRendererReturning('PROD'),
            jsonRenderer: $this->createRendererReturning('JSON'),
        );

        $request = new ServerRequest('GET', '/', ['Accept' => 'application/json']);
        $output = $handler->handle(new \RuntimeException('test'), $request);

        self::assertSame('JSON', $output);
    }

    #[Test]
    public function contextIsDevelopmentTrueInDevMode(): void
    {
        $handler = $this->makeHandler(environment: 'development');

        $capturedContext = null;
        $renderer = new class ($capturedContext) implements RendererInterface {
            /** @param ErrorContext|null &$ref */
            public function __construct(private mixed &$ref) {}
            public function render(ErrorContext $context): string
            {
                $this->ref = $context;
                return '';
            }
        };
        $handler->setDevRenderer($renderer);
        $handler->handle(new \RuntimeException('test'));

        self::assertNotNull($capturedContext);
        self::assertTrue($capturedContext->isDevelopment);
    }

    #[Test]
    public function contextIsDevelopmentFalseInProdMode(): void
    {
        $handler = $this->makeHandler(environment: 'production');

        $capturedContext = null;
        $renderer = new class ($capturedContext) implements RendererInterface {
            /** @param ErrorContext|null &$ref */
            public function __construct(private mixed &$ref) {}
            public function render(ErrorContext $context): string
            {
                $this->ref = $context;
                return '';
            }
        };
        $handler->setProdRenderer($renderer);
        $handler->handle(new \RuntimeException('test'));

        self::assertNotNull($capturedContext);
        self::assertFalse($capturedContext->isDevelopment);
    }

    #[Test]
    public function logsExceptionMessageCorrectly(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('log')
            ->with(
                self::anything(),
                'Exact error message here',
                self::anything(),
            );

        $handler = $this->makeHandler();
        $handler->setLogger($logger);
        $handler->handle(new \RuntimeException('Exact error message here'));
    }

    #[Test]
    public function sensitiveKeyMatchingIsCaseInsensitive(): void
    {
        $handler = $this->makeHandler(environment: 'development');

        $original = $_SERVER;
        $_SERVER['app_password'] = 'secret';
        $_SERVER['App_Secret'] = 'hidden';

        try {
            $capturedContext = null;
            $renderer = new class ($capturedContext) implements RendererInterface {
                /** @param ErrorContext|null &$ref */
                public function __construct(private mixed &$ref) {}
                public function render(ErrorContext $context): string
                {
                    $this->ref = $context;
                    return '';
                }
            };
            $handler->setDevRenderer($renderer);
            $handler->handle(new \RuntimeException('test'));

            self::assertNotNull($capturedContext);
            self::assertSame('********', $capturedContext->environment['app_password']);
            self::assertSame('********', $capturedContext->environment['App_Secret']);
        } finally {
            $_SERVER = $original;
        }
    }

    #[Test]
    public function nonStringServerValuesAreSkipped(): void
    {
        $handler = $this->makeHandler(environment: 'development');

        $original = $_SERVER;
        $_SERVER['ARRAY_VALUE'] = ['not', 'a', 'string'];
        $_SERVER['SAFE_SETTING'] = 'visible';

        try {
            $capturedContext = null;
            $renderer = new class ($capturedContext) implements RendererInterface {
                /** @param ErrorContext|null &$ref */
                public function __construct(private mixed &$ref) {}
                public function render(ErrorContext $context): string
                {
                    $this->ref = $context;
                    return '';
                }
            };
            $handler->setDevRenderer($renderer);
            $handler->handle(new \RuntimeException('test'));

            self::assertNotNull($capturedContext);
            self::assertArrayNotHasKey('ARRAY_VALUE', $capturedContext->environment);
            self::assertSame('visible', $capturedContext->environment['SAFE_SETTING']);
        } finally {
            $_SERVER = $original;
        }
    }

    private function makeHandler(string $environment = 'development'): ExceptionHandler
    {
        return new ExceptionHandler(
            environment: $environment,
            devRenderer: new HtmlDevRenderer(),
            prodRenderer: new HtmlProdRenderer(),
            jsonRenderer: new JsonRenderer(),
        );
    }

    private function createRendererReturning(string $output): RendererInterface
    {
        return new class ($output) implements RendererInterface {
            public function __construct(private readonly string $output) {}
            public function render(ErrorContext $context): string
            {
                return $this->output;
            }
        };
    }
}
