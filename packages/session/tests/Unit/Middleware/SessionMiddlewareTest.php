<?php

declare(strict_types=1);

namespace PHPdot\Session\Tests\Unit\Middleware;

use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\Http\Message\ServerRequest;
use PHPdot\Session\Handler\ArrayHandler;
use PHPdot\Session\Middleware\SessionMiddleware;
use PHPdot\Session\Session;
use PHPdot\Session\SessionConfig;
use PHPdot\Session\SessionManager;
use PHPdot\Session\Tests\Stubs\StubRequestHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionMiddlewareTest extends TestCase
{
    private ArrayHandler $handler;
    private SessionConfig $config;
    private SessionManager $manager;
    private SessionMiddleware $middleware;

    protected function setUp(): void
    {
        $this->handler = new ArrayHandler();
        $this->config = new SessionConfig(
            name: 'test_session',
            lifetime: 3600,
            secure: false,
            gcProbability: 0,
        );
        $this->manager = new SessionManager($this->handler, $this->config);
        $this->middleware = new SessionMiddleware($this->manager);
    }

    private function createRequest(string $cookieValue = ''): ServerRequest
    {
        $request = new ServerRequest('GET', '/');

        if ($cookieValue !== '') {
            $request = $request->withCookieParams(['test_session' => $cookieValue]);
        }

        return $request;
    }

    private function createStubHandler(): StubRequestHandler
    {
        $factory = new ResponseFactory();

        return new StubRequestHandler($factory->createResponse(200));
    }

    #[Test]
    public function attachesSessionToRequestAttribute(): void
    {
        $stub = $this->createStubHandler();

        $this->middleware->process($this->createRequest(), $stub);

        self::assertNotNull($stub->capturedRequest);
        self::assertInstanceOf(Session::class, $stub->capturedRequest->getAttribute(SessionMiddleware::ATTRIBUTE));
    }

    #[Test]
    public function setsSetCookieHeaderOnResponse(): void
    {
        $stub = $this->createStubHandler();

        $response = $this->middleware->process($this->createRequest(), $stub);

        $cookies = $response->getHeader('Set-Cookie');

        self::assertNotEmpty($cookies);
        self::assertStringContainsString('test_session=', $cookies[0]);
    }

    #[Test]
    public function createsNewSessionWhenNoCookie(): void
    {
        $stub = $this->createStubHandler();

        $this->middleware->process($this->createRequest(), $stub);

        self::assertNotNull($stub->capturedRequest);
        $session = $stub->capturedRequest->getAttribute(SessionMiddleware::ATTRIBUTE);

        self::assertInstanceOf(Session::class, $session);
        self::assertTrue($session->isStarted());
    }

    #[Test]
    public function resumesSessionWhenValidCookiePresent(): void
    {
        $initial = $this->manager->start(null);
        $initial->set('user_id', 42);
        $this->manager->save($initial);

        $stub = $this->createStubHandler();

        $this->middleware->process($this->createRequest($initial->id()), $stub);

        self::assertNotNull($stub->capturedRequest);
        $session = $stub->capturedRequest->getAttribute(SessionMiddleware::ATTRIBUTE);

        self::assertInstanceOf(Session::class, $session);
        self::assertSame(42, $session->get('user_id'));
    }

    #[Test]
    public function createsNewSessionWhenInvalidCookieFormat(): void
    {
        $stub = $this->createStubHandler();

        $this->middleware->process($this->createRequest('invalid!'), $stub);

        self::assertNotNull($stub->capturedRequest);
        $session = $stub->capturedRequest->getAttribute(SessionMiddleware::ATTRIBUTE);

        self::assertInstanceOf(Session::class, $session);
        self::assertTrue($session->isStarted());
    }

    #[Test]
    public function createsNewSessionWhenSessionExpired(): void
    {
        $config = new SessionConfig(
            name: 'test_session',
            lifetime: 1,
            secure: false,
            gcProbability: 0,
        );
        $manager = new SessionManager($this->handler, $config);
        $middleware = new SessionMiddleware($manager);

        $initial = $manager->start(null);
        $this->handler->write(
            $initial->id(),
            json_encode([
                'data' => [],
                'flash_new' => [],
                'flash_old' => [],
                'token' => null,
                'created_at' => time() - 100,
                'last_activity' => time() - 100,
            ], JSON_THROW_ON_ERROR),
            0,
        );

        $stub = $this->createStubHandler();

        $middleware->process($this->createRequest($initial->id()), $stub);

        self::assertNotNull($stub->capturedRequest);
        $session = $stub->capturedRequest->getAttribute(SessionMiddleware::ATTRIBUTE);

        self::assertInstanceOf(Session::class, $session);
        self::assertNotSame($initial->id(), $session->id());
    }

    #[Test]
    public function sessionDataPersistsAcrossRequests(): void
    {
        $stub1 = $this->createStubHandler();
        $response1 = $this->middleware->process($this->createRequest(), $stub1);

        self::assertNotNull($stub1->capturedRequest);
        $session1 = $stub1->capturedRequest->getAttribute(SessionMiddleware::ATTRIBUTE);
        self::assertInstanceOf(Session::class, $session1);
        $session1->set('counter', 1);
        $this->manager->save($session1);

        $stub2 = $this->createStubHandler();
        $this->middleware->process($this->createRequest($session1->id()), $stub2);

        self::assertNotNull($stub2->capturedRequest);
        $session2 = $stub2->capturedRequest->getAttribute(SessionMiddleware::ATTRIBUTE);
        self::assertInstanceOf(Session::class, $session2);

        self::assertSame(1, $session2->get('counter'));
    }

    #[Test]
    public function flashDataAvailableNextRequestGoneAfter(): void
    {
        $session1 = $this->manager->start(null);
        $session1->flash('msg', 'hello');
        $this->manager->save($session1);

        // Request 2: flash should be available during handler execution
        $flashValue2 = null;
        $sessionId2 = null;
        $factory = new ResponseFactory();
        $handler2 = new class ($factory->createResponse(200), $flashValue2, $sessionId2) implements \Psr\Http\Server\RequestHandlerInterface {
            public function __construct(
                private readonly \Psr\Http\Message\ResponseInterface $response,
                private mixed &$flashCapture,
                private mixed &$idCapture,
            ) {}

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE);

                if ($session instanceof Session) {
                    $this->flashCapture = $session->getFlash('msg');
                    $this->idCapture = $session->id();
                }

                return $this->response;
            }
        };

        $this->middleware->process($this->createRequest($session1->id()), $handler2);

        self::assertSame('hello', $flashValue2);
        self::assertNotNull($sessionId2);

        // Request 3: flash should be gone
        $flashValue3 = 'not-null-sentinel';
        $handler3 = new class ($factory->createResponse(200), $flashValue3) implements \Psr\Http\Server\RequestHandlerInterface {
            public function __construct(
                private readonly \Psr\Http\Message\ResponseInterface $response,
                private mixed &$flashCapture,
            ) {}

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE);

                if ($session instanceof Session) {
                    $this->flashCapture = $session->getFlash('msg');
                }

                return $this->response;
            }
        };

        $this->middleware->process($this->createRequest($sessionId2), $handler3);

        self::assertNull($flashValue3);
    }

    #[Test]
    public function cookieNameMatchesConfig(): void
    {
        $stub = $this->createStubHandler();

        $response = $this->middleware->process($this->createRequest(), $stub);

        $cookie = $response->getHeader('Set-Cookie')[0] ?? '';

        self::assertStringStartsWith('test_session=', $cookie);
    }

    #[Test]
    public function cookieAttributesMatchConfig(): void
    {
        $config = new SessionConfig(
            name: 'sid',
            lifetime: 7200,
            path: '/app',
            domain: '.example.com',
            secure: true,
            httpOnly: true,
            sameSite: 'Strict',
        );
        $manager = new SessionManager($this->handler, $config);
        $middleware = new SessionMiddleware($manager);

        $stub = $this->createStubHandler();
        $response = $middleware->process(
            (new ServerRequest('GET', '/'))->withCookieParams([]),
            $stub,
        );

        $cookie = $response->getHeader('Set-Cookie')[0] ?? '';

        self::assertStringContainsString('Path=/app', $cookie);
        self::assertStringContainsString('Domain=.example.com', $cookie);
        self::assertStringContainsString('Max-Age=7200', $cookie);
        self::assertStringContainsString('Secure', $cookie);
        self::assertStringContainsString('HttpOnly', $cookie);
        self::assertStringContainsString('SameSite=Strict', $cookie);
    }

    #[Test]
    public function regeneratedSessionGetsNewCookieValue(): void
    {
        $initial = $this->manager->start(null);
        $this->manager->save($initial);
        $originalId = $initial->id();

        $stub = $this->createStubHandler();
        $request = $this->createRequest($originalId);

        $factory = new ResponseFactory();
        $innerHandler = new StubRequestHandler($factory->createResponse(200));

        $response = $this->middleware->process($request, new class ($factory->createResponse(200)) implements \Psr\Http\Server\RequestHandlerInterface {
            public function __construct(private readonly \Psr\Http\Message\ResponseInterface $response) {}

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE);

                if ($session instanceof Session) {
                    $session->regenerate();
                }

                return $this->response;
            }
        });

        $cookie = $response->getHeader('Set-Cookie')[0] ?? '';

        self::assertStringNotContainsString($originalId, $cookie);
    }

    #[Test]
    public function attributeConstant(): void
    {
        self::assertSame('session', SessionMiddleware::ATTRIBUTE);
    }

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new \ReflectionClass(SessionMiddleware::class);

        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function responseBodyIsNotModified(): void
    {
        $factory = new ResponseFactory();
        $body = $factory->createStream('Hello World');
        $innerResponse = $factory->createResponse(200)->withBody($body);
        $stub = new StubRequestHandler($innerResponse);

        $response = $this->middleware->process($this->createRequest(), $stub);

        self::assertSame('Hello World', (string) $response->getBody());
    }
}
