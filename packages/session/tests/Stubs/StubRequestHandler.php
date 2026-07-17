<?php

declare(strict_types=1);

namespace PHPdot\Session\Tests\Stubs;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Simple PSR-15 handler that captures the request and returns a fixed response.
 */
final class StubRequestHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $capturedRequest = null;

    public function __construct(
        private readonly ResponseInterface $response,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->capturedRequest = $request;

        return $this->response;
    }
}
