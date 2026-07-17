<?php

declare(strict_types=1);

namespace PHPdot\HttpMiddleware\Tests\Unit;

use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\HttpMiddleware\JsonBodyMiddleware;
use PHPdot\HttpMiddleware\Tests\Support\SpyHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class JsonBodyMiddlewareTest extends TestCase
{
    private ResponseFactory $psr17;
    private JsonBodyMiddleware $middleware;

    protected function setUp(): void
    {
        $this->psr17 = new ResponseFactory();
        $this->middleware = new JsonBodyMiddleware($this->psr17, $this->psr17);
    }

    public function testParsesValidJsonObjectIntoParsedBody(): void
    {
        $spy = new SpyHandler($this->psr17);

        $this->middleware->process($this->jsonRequest('application/json', '{"name":"Ada","age":36}'), $spy);

        self::assertNotNull($spy->received);
        self::assertSame(['name' => 'Ada', 'age' => 36], $spy->received->getParsedBody());
    }

    public function testRecognisesJsonContentTypeWithCharsetSuffix(): void
    {
        $spy = new SpyHandler($this->psr17);

        $this->middleware->process($this->jsonRequest('application/json; charset=utf-8', '{"ok":true}'), $spy);

        self::assertNotNull($spy->received);
        self::assertSame(['ok' => true], $spy->received->getParsedBody());
    }

    public function testMalformedJsonReturns400AndDoesNotCallHandler(): void
    {
        $spy = new SpyHandler($this->psr17);

        $response = $this->middleware->process($this->jsonRequest('application/json', '{"name":'), $spy);

        self::assertSame(400, $response->getStatusCode());
        self::assertNull($spy->received, 'the handler must not run when the body is malformed');
    }

    public function testNonJsonRequestIsLeftUntouched(): void
    {
        $spy = new SpyHandler($this->psr17);

        $this->middleware->process($this->jsonRequest('application/x-www-form-urlencoded', 'name=Ada'), $spy);

        self::assertNotNull($spy->received);
        self::assertNull($spy->received->getParsedBody());
    }

    public function testEmptyBodyPassesThroughWithoutParsing(): void
    {
        $spy = new SpyHandler($this->psr17);

        $response = $this->middleware->process($this->jsonRequest('application/json', ''), $spy);

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($spy->received);
        self::assertNull($spy->received->getParsedBody());
    }

    public function testScalarJsonIsNotSetAsParsedBody(): void
    {
        $spy = new SpyHandler($this->psr17);

        $this->middleware->process($this->jsonRequest('application/json', '42'), $spy);

        self::assertNotNull($spy->received);
        self::assertNull($spy->received->getParsedBody());
    }

    private function jsonRequest(string $contentType, string $body): ServerRequestInterface
    {
        return $this->psr17->createServerRequest('POST', '/')
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->psr17->createStream($body));
    }
}
