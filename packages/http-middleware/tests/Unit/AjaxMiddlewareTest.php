<?php

declare(strict_types=1);

namespace PHPdot\HttpMiddleware\Tests\Unit;

use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\HttpMiddleware\AjaxMiddleware;
use PHPdot\HttpMiddleware\Tests\Support\SpyHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class AjaxMiddlewareTest extends TestCase
{
    private ResponseFactory $psr17;
    private AjaxMiddleware $middleware;

    protected function setUp(): void
    {
        $this->psr17 = new ResponseFactory();
        $this->middleware = new AjaxMiddleware($this->psr17);
    }

    public function testAdmitsJqueryStyleRequests(): void
    {
        self::assertTrue($this->admitted(['X-Requested-With' => 'XMLHttpRequest']));
    }

    public function testHeaderComparisonIsCaseInsensitive(): void
    {
        self::assertTrue($this->admitted(['X-Requested-With' => 'xmlhttprequest']));
    }

    /**
     * The case that decides whether this middleware is usable at all: the
     * native fetch API sends no X-Requested-With, so without Sec-Fetch-Dest
     * it would 404 this platform's own data tables.
     */
    public function testAdmitsNativeFetchViaSecFetchDest(): void
    {
        self::assertTrue($this->admitted(['Sec-Fetch-Dest' => 'empty', 'Accept' => '*/*']));
    }

    public function testAdmitsExplicitJsonCallers(): void
    {
        self::assertTrue($this->admitted(['Accept' => 'application/json']));
    }

    public function testRejectsATopLevelNavigation(): void
    {
        self::assertFalse($this->admitted([
            'Sec-Fetch-Dest' => 'document',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ]));
    }

    /**
     * A browser navigation advertises json among everything else, so
     * json-anywhere-in-Accept cannot be sufficient on its own.
     */
    public function testRejectsNavigationThatAlsoAcceptsJson(): void
    {
        self::assertFalse($this->admitted(['Accept' => 'text/html,application/json;q=0.9']));
    }

    public function testRejectsAPlainRequest(): void
    {
        self::assertFalse($this->admitted([]));
    }

    public function testRejectionIsA404AndNeverReachesTheHandler(): void
    {
        $spy = new SpyHandler($this->psr17);
        $response = $this->middleware->process($this->request([]), $spy);

        self::assertSame(404, $response->getStatusCode());
        self::assertNull($spy->received, 'a rejected request must not reach the handler');
        self::assertSame('', (string) $response->getBody(), 'a route that answers nothing explains nothing');
    }

    public function testAdmittedRequestReachesTheHandlerUnchanged(): void
    {
        $spy = new SpyHandler($this->psr17);
        $response = $this->middleware->process($this->request(['Sec-Fetch-Dest' => 'empty']), $spy);

        self::assertNotNull($spy->received);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testStaticCheckAgreesWithTheMiddleware(): void
    {
        $request = $this->request(['Sec-Fetch-Dest' => 'empty']);

        self::assertTrue(AjaxMiddleware::isAjax($request));
        self::assertTrue($this->admitted(['Sec-Fetch-Dest' => 'empty']));
    }

    /**
     * Whether the request got through to the handler.
     *
     * @param array<string, string> $headers
     */
    private function admitted(array $headers): bool
    {
        $spy = new SpyHandler($this->psr17);
        $this->middleware->process($this->request($headers), $spy);

        return $spy->received !== null;
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(array $headers): ServerRequestInterface
    {
        $request = $this->psr17->createServerRequest('GET', 'https://example.test/data');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }
}
