<?php

declare(strict_types=1);

/**
 * Global error handler with one-line setup.
 *
 * Registers set_exception_handler, set_error_handler, and register_shutdown_function.
 * Converts PHP errors to exceptions. Catches fatal errors on shutdown.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler;

use PHPdot\ErrorHandler\Contract\ContextProviderInterface;
use PHPdot\ErrorHandler\Contract\RendererInterface;
use PHPdot\ErrorHandler\Contract\SolutionProviderInterface;
use PHPdot\ErrorHandler\Exception\FatalErrorException;
use PHPdot\ErrorHandler\Renderer\HtmlDevRenderer;
use PHPdot\ErrorHandler\Renderer\HtmlProdRenderer;
use PHPdot\ErrorHandler\Renderer\JsonRenderer;
use PHPdot\ErrorHandler\Renderer\PlainTextRenderer;
use Psr\Log\LoggerInterface;

final class ErrorHandler
{
    private readonly ExceptionHandler $exceptionHandler;

    /**
     * Assemble the default renderer stack for the given environment.
     *
     * @param string $environment 'development' shows debug pages; anything else is production
     */
    private function __construct(string $environment)
    {
        $isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';

        $this->exceptionHandler = new ExceptionHandler(
            environment: $environment,
            devRenderer: $isCli ? new PlainTextRenderer() : new HtmlDevRenderer(),
            prodRenderer: $isCli ? new PlainTextRenderer() : new HtmlProdRenderer(),
            jsonRenderer: new JsonRenderer(),
        );
    }

    /**
     * Register the error handler globally. One line, works immediately.
     *
     * @param string $environment 'development' or 'production'
     *
     * @return self
     */
    public static function register(string $environment = 'production'): self
    {
        $instance = new self($environment);

        set_exception_handler(function (\Throwable $exception) use ($instance): void {
            $output = $instance->exceptionHandler->handle($exception);
            if (!headers_sent()) {
                $statusCode = $instance->exceptionHandler->getStatusCode($exception);
                http_response_code($statusCode);

                $contentType = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg'
                    ? 'text/plain'
                    : 'text/html';
                header('Content-Type: ' . $contentType . '; charset=UTF-8');
            }
            echo $output;
        });

        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(function () use ($instance): void {
            $error = error_get_last();
            if ($error === null) {
                return;
            }

            $fatalTypes = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;
            if (($error['type'] & $fatalTypes) === 0) {
                return;
            }

            $exception = FatalErrorException::fromLastError($error);
            $output = $instance->exceptionHandler->handle($exception);

            if (!headers_sent()) {
                http_response_code($instance->exceptionHandler->getStatusCode($exception));
                $contentType = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg'
                    ? 'text/plain'
                    : 'text/html';
                header('Content-Type: ' . $contentType . '; charset=UTF-8');
            }

            echo $output;
        });

        return $instance;
    }

    /**
     * Set PSR-3 logger for error logging.
     *
     * @param LoggerInterface $logger
     *
     * @return ErrorHandler
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->exceptionHandler->setLogger($logger);

        return $this;
    }

    /**
     * Set the development renderer (debug page).
     *
     * @param RendererInterface $renderer
     *
     * @return ErrorHandler
     */
    public function setDevRenderer(RendererInterface $renderer): self
    {
        $this->exceptionHandler->setDevRenderer($renderer);

        return $this;
    }

    /**
     * Set the production renderer (clean error page).
     *
     * @param RendererInterface $renderer
     *
     * @return ErrorHandler
     */
    public function setProdRenderer(RendererInterface $renderer): self
    {
        $this->exceptionHandler->setProdRenderer($renderer);

        return $this;
    }

    /**
     * Set the JSON renderer (API errors).
     *
     * @param RendererInterface $renderer
     *
     * @return ErrorHandler
     */
    public function setJsonRenderer(RendererInterface $renderer): self
    {
        $this->exceptionHandler->setJsonRenderer($renderer);

        return $this;
    }

    /**
     * Add a context provider (extra debug tabs).
     *
     * @param ContextProviderInterface $provider
     *
     * @return ErrorHandler
     */
    public function addContextProvider(ContextProviderInterface $provider): self
    {
        $this->exceptionHandler->addContextProvider($provider);

        return $this;
    }

    /**
     * Add a solution provider (suggested fixes).
     *
     * @param SolutionProviderInterface $provider
     *
     * @return ErrorHandler
     */
    public function addSolutionProvider(SolutionProviderInterface $provider): self
    {
        $this->exceptionHandler->addSolutionProvider($provider);

        return $this;
    }

    /**
     * Set environment variables to hide in debug output.
     *
     * @param list<string> $keys
     *
     * @return ErrorHandler
     */
    public function setSensitiveKeys(array $keys): self
    {
        $this->exceptionHandler->setSensitiveKeys($keys);

        return $this;
    }

    /**
     * Get the underlying ExceptionHandler.
     *
     * @return ExceptionHandler
     */
    public function getExceptionHandler(): ExceptionHandler
    {
        return $this->exceptionHandler;
    }
}
