<?php

declare(strict_types=1);

/**
 * JsonBodyMiddleware
 *
 * Parses an application/json request body into the PSR-7 parsed body, so handlers
 * read getParsedBody() instead of hand-decoding. A malformed body is rejected with
 * 400 here (not a misleading validation error downstream). Non-JSON requests, empty
 * bodies, and top-level scalars pass through untouched.
 *
 * Framework-agnostic: depends only on PSR-7/15 plus PSR-17 factories for the 400,
 * so it runs in any PSR-15 stack.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\HttpMiddleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class JsonBodyMiddleware implements MiddlewareInterface
{
    /**
     * Wire the middleware to the PSR-17 factories it uses for error responses.
     *
     * @param ResponseFactoryInterface $responses Creates the 400 response on malformed JSON
     * @param StreamFactoryInterface $streams Creates the error response body stream
     */
    public function __construct(
        private readonly ResponseFactoryInterface $responses,
        private readonly StreamFactoryInterface $streams,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!str_contains(strtolower($request->getHeaderLine('Content-Type')), 'application/json')) {
            return $handler->handle($request);
        }

        $raw = (string) $request->getBody();

        if (trim($raw) === '') {
            return $handler->handle($request);
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->badRequest('Malformed JSON request body.');
        }

        if (is_array($decoded)) {
            $request = $request->withParsedBody($decoded);
        }

        return $handler->handle($request);
    }

    /**
     * Bad request.
     *
     * @param string $message
     *
     * @return ResponseInterface
     */
    private function badRequest(string $message): ResponseInterface
    {
        $body = $this->streams->createStream(json_encode(['error' => $message], JSON_THROW_ON_ERROR));

        return $this->responses->createResponse(400)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }
}
