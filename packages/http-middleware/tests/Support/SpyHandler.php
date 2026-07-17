<?php

declare(strict_types=1);

/**
 * SpyHandler
 *
 * A PSR-15 request handler test double: records the request it was handed (so a
 * test can assert what the middleware passed downstream) and returns a 200.
 * `$received` stays null when the handler is never reached — e.g. a middleware
 * short-circuited with its own response.
 */

namespace PHPdot\HttpMiddleware\Tests\Support;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SpyHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $received = null;

    public function __construct(
        private readonly ResponseFactoryInterface $responses,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->received = $request;

        return $this->responses->createResponse(200);
    }
}
