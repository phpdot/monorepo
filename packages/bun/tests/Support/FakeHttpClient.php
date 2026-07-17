<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client test double mapping exact URLs to canned (status, body) pairs. Counts requests so
 * tests can assert no double-download.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var array<string, array{status: int, body: string}> */
    private array $routes = [];

    /** @var array<string, int> */
    public array $hits = [];

    public function __construct(private readonly Psr17Factory $factory = new Psr17Factory()) {}

    public function map(string $url, string $body, int $status = 200): void
    {
        $this->routes[$url] = ['status' => $status, 'body' => $body];
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $url = (string) $request->getUri();
        $this->hits[$url] = ($this->hits[$url] ?? 0) + 1;

        if (!isset($this->routes[$url])) {
            return $this->factory->createResponse(404)->withBody($this->factory->createStream('not found'));
        }

        $route = $this->routes[$url];

        return $this->factory->createResponse($route['status'])
            ->withBody($this->factory->createStream($route['body']));
    }
}
