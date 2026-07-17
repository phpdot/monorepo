<?php

declare(strict_types=1);

/**
 * Core exception handler: collects context, logs, and renders.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler;

use PHPdot\ErrorHandler\Context\ContextTab;
use PHPdot\ErrorHandler\Context\ErrorContext;
use PHPdot\ErrorHandler\Context\StackTrace;
use PHPdot\ErrorHandler\Contract\ContextProviderInterface;
use PHPdot\ErrorHandler\Contract\RendererInterface;
use PHPdot\ErrorHandler\Contract\SolutionProviderInterface;
use PHPdot\ErrorHandler\Solution\Solution;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class ExceptionHandler
{
    /**
     * @var list<ContextProviderInterface>
     */
    private array $contextProviders = [];

    /**
     * @var list<SolutionProviderInterface>
     */
    private array $solutionProviders = [];

    /**
     * @var list<string>
     */
    private array $sensitiveKeys = [
        'PASSWORD', 'SECRET', 'KEY', 'TOKEN', 'CREDENTIAL',
        'DB_PASSWORD', 'APP_KEY', 'AWS_SECRET', 'API_KEY',
        'PRIVATE_KEY', 'AUTH_TOKEN',
    ];

    /**
     * Wire the handler to its renderers and optional logger.
     *
     * @param string $environment 'development' shows debug pages; any other value is production
     * @param RendererInterface $devRenderer Renderer for the development HTML debug page
     * @param RendererInterface $prodRenderer Renderer for the production HTML page
     * @param RendererInterface $jsonRenderer Renderer for RFC 9457 JSON responses
     * @param ?LoggerInterface $logger PSR-3 logger; when null, exceptions are not logged
     */
    public function __construct(
        private readonly string $environment,
        private RendererInterface $devRenderer,
        private RendererInterface $prodRenderer,
        private RendererInterface $jsonRenderer,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Handle an exception and return rendered output.
     *
     * @param \Throwable $exception
     * @param ?ServerRequestInterface $request
     *
     * @return string
     */
    public function handle(\Throwable $exception, ?ServerRequestInterface $request = null): string
    {
        $context = $this->buildContext($exception, $request);

        $this->log($exception, $context);

        if ($this->wantsJson($request)) {
            return $this->jsonRenderer->render($context);
        }

        if ($this->environment === 'development') {
            return $this->devRenderer->render($context);
        }

        return $this->prodRenderer->render($context);
    }

    /**
     * Get the HTTP status code for an exception.
     *
     * @param \Throwable $exception
     *
     * @return int
     */
    public function getStatusCode(\Throwable $exception): int
    {
        if (method_exists($exception, 'getStatusCode')) {
            $code = $exception->getStatusCode();
            if (is_int($code) && $code >= 100 && $code < 600) {
                return $code;
            }
        }

        return match (true) {
            $exception instanceof \InvalidArgumentException => 400,
            $exception instanceof \DomainException => 422,
            $exception instanceof \RuntimeException,
            $exception instanceof \LogicException,
            $exception instanceof \TypeError => 500,
            default => 500,
        };
    }

    /**
     * Set PSR-3 logger.
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Set the development renderer.
     *
     * @param RendererInterface $renderer
     *
     * @return void
     */
    public function setDevRenderer(RendererInterface $renderer): void
    {
        $this->devRenderer = $renderer;
    }

    /**
     * Set the production renderer.
     *
     * @param RendererInterface $renderer
     *
     * @return void
     */
    public function setProdRenderer(RendererInterface $renderer): void
    {
        $this->prodRenderer = $renderer;
    }

    /**
     * Set the JSON renderer.
     *
     * @param RendererInterface $renderer
     *
     * @return void
     */
    public function setJsonRenderer(RendererInterface $renderer): void
    {
        $this->jsonRenderer = $renderer;
    }

    /**
     * Add a context provider (extra debug tabs).
     *
     * @param ContextProviderInterface $provider
     *
     * @return void
     */
    public function addContextProvider(ContextProviderInterface $provider): void
    {
        $this->contextProviders[] = $provider;
    }

    /**
     * Add a solution provider (suggested fixes).
     *
     * @param SolutionProviderInterface $provider
     *
     * @return void
     */
    public function addSolutionProvider(SolutionProviderInterface $provider): void
    {
        $this->solutionProviders[] = $provider;
    }

    /**
     * Set environment variable keys to hide in debug output.
     *
     * @param list<string> $keys
     *
     * @return void
     */
    public function setSensitiveKeys(array $keys): void
    {
        $this->sensitiveKeys = $keys;
    }

    /**
     * Get the current environment mode.
     *
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Build the full ErrorContext for rendering.
     *
     * @param \Throwable $exception
     * @param ?ServerRequestInterface $request
     *
     * @return ErrorContext
     */
    private function buildContext(\Throwable $exception, ?ServerRequestInterface $request): ErrorContext
    {
        return new ErrorContext(
            exception: $exception,
            stackTrace: StackTrace::fromException($exception),
            statusCode: $this->getStatusCode($exception),
            request: $request,
            environment: $this->collectEnvironment(),
            context: $this->collectContext($exception, $request),
            solutions: $this->collectSolutions($exception),
            isDevelopment: $this->environment === 'development',
        );
    }

    /**
     * Collect environment variables with sensitive keys filtered.
     *
     * @return array<string, string>
     */
    private function collectEnvironment(): array
    {
        /**
         * @var array<string, string> $env
         */
        $env = [];

        foreach ($_SERVER as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            $env[$key] = $this->isSensitive($key) ? '********' : $value;
        }

        return $env;
    }

    /**
     * Check if an environment key contains sensitive information.
     *
     * @param string $key
     *
     * @return bool
     */
    private function isSensitive(string $key): bool
    {
        $upper = strtoupper($key);
        foreach ($this->sensitiveKeys as $sensitive) {
            if (str_contains($upper, strtoupper($sensitive))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect context tabs from all registered providers.
     *
     * @param \Throwable $exception
     * @param ?ServerRequestInterface $request
     *
     * @return list<ContextTab>
     */
    private function collectContext(\Throwable $exception, ?ServerRequestInterface $request): array
    {
        $tabs = [];

        foreach ($this->contextProviders as $provider) {
            try {
                $data = $provider->collect($exception, $request);
                if ($data !== []) {
                    $tabs[] = new ContextTab(
                        label: $provider->getLabel(),
                        data: $data,
                    );
                }
            } catch (\Throwable) {
            }
        }

        return $tabs;
    }

    /**
     * Collect solutions from all registered providers.
     *
     * @param \Throwable $exception
     *
     * @return list<Solution>
     */
    private function collectSolutions(\Throwable $exception): array
    {
        $solutions = [];

        foreach ($this->solutionProviders as $provider) {
            try {
                if ($provider->canSolve($exception)) {
                    $solutions = [...$solutions, ...$provider->getSolutions($exception)];
                }
            } catch (\Throwable) {
            }
        }

        return $solutions;
    }

    /**
     * Log the exception via PSR-3.
     *
     * @param \Throwable $exception
     * @param ErrorContext $context
     *
     * @return void
     */
    private function log(\Throwable $exception, ErrorContext $context): void
    {
        if ($this->logger === null) {
            return;
        }

        $level = $this->getLogLevel($context->statusCode);

        $this->logger->log($level, $exception->getMessage(), [
            'exception' => $exception,
            'status_code' => $context->statusCode,
        ]);
    }

    /**
     * Map HTTP status code to PSR-3 log level.
     *
     * @param int $statusCode
     *
     * @return string
     */
    private function getLogLevel(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => LogLevel::ERROR,
            $statusCode >= 400 => LogLevel::WARNING,
            default => LogLevel::NOTICE,
        };
    }

    /**
     * Determine if the request expects a JSON response.
     *
     * @param ?ServerRequestInterface $request
     *
     * @return bool
     */
    private function wantsJson(?ServerRequestInterface $request): bool
    {
        if ($request === null) {
            return false;
        }

        $accept = $request->getHeaderLine('Accept');

        return str_contains($accept, 'application/json')
            || str_contains($accept, 'application/problem+json');
    }
}
