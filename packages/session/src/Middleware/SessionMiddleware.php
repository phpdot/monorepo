<?php

declare(strict_types=1);

/**
 * PSR-15 middleware that manages the session lifecycle.
 *
 * 1. Reads the session cookie from the request
 * 2. Starts or resumes the session via SessionManager
 * 3. Attaches the Session to the request as an attribute
 * 4. Processes the request through the pipeline
 * 5. Saves the session and attaches the Set-Cookie header
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Session\Middleware;

use PHPdot\Container\Attribute\Scoped;
use PHPdot\Session\Exception\SessionExpiredException;
use PHPdot\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Scoped]
final class SessionMiddleware implements MiddlewareInterface
{
    /**
     * Request attribute name where the Session is stored.
     */
    public const string ATTRIBUTE = 'session';

    /**
     * PSR-15 middleware that loads the session and writes the cookie around the request.
     *
     * @param SessionManager $manager
     */
    public function __construct(
        private readonly SessionManager $manager,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookieId = $this->readCookie($request);

        try {
            $session = $this->manager->start($cookieId);
        } catch (SessionExpiredException) {
            $session = $this->manager->start(null);
        }

        $previousId = $cookieId;

        $request = $request->withAttribute(self::ATTRIBUTE, $session);

        try {
            $response = $handler->handle($request);
        } finally {
            if ($session->isDestroyed() && $previousId !== null) {
                $this->manager->destroy($previousId);
            }

            $this->manager->save($session);
        }

        return $response->withAddedHeader(
            'Set-Cookie',
            $this->manager->cookieHeader($session),
        );
    }

    /**
     * Read the session cookie value from the request.
     *
     * @param ServerRequestInterface $request
     *
     * @return ?string
     */
    private function readCookie(ServerRequestInterface $request): ?string
    {
        $cookies = $request->getCookieParams();
        $name = $this->manager->getConfig()->name;

        if (!isset($cookies[$name])) {
            return null;
        }

        $value = $cookies[$name];

        if (!\is_string($value)) {
            return null;
        }

        return $value;
    }
}
