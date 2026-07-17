<?php

declare(strict_types=1);

/**
 * PSR-15 middleware that catches exceptions and returns error responses.
 *
 * Wraps the entire application pipeline. Catches any Throwable,
 * renders it via ExceptionHandler, and returns a PSR-7 Response.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\ErrorHandler\Middleware;

use PHPdot\ErrorHandler\ExceptionHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    /**
     * Wire the middleware to its handler and PSR-17 factories.
     *
     * @param ExceptionHandler $handler Builds the rendered error body
     * @param ResponseFactoryInterface $responseFactory Creates the PSR-7 response
     * @param StreamFactoryInterface $streamFactory Creates the response body stream
     */
    public function __construct(
        private readonly ExceptionHandler $handler,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            $statusCode = $this->handler->getStatusCode($e);
            $body = $this->handler->handle($e, $request);

            $contentType = $this->wantsJson($request)
                ? 'application/problem+json'
                : 'text/html';

            $response = $this->responseFactory->createResponse($statusCode);
            $stream = $this->streamFactory->createStream($body);

            return $response
                ->withHeader('Content-Type', $contentType . '; charset=UTF-8')
                ->withBody($stream);
        }
    }

    /**
     * Wants json.
     *
     * @param ServerRequestInterface $request
     *
     * @return bool
     */
    private function wantsJson(ServerRequestInterface $request): bool
    {
        $accept = $request->getHeaderLine('Accept');

        return str_contains($accept, 'application/json')
            || str_contains($accept, 'application/problem+json');
    }
}
