<?php

declare(strict_types=1);

/**
 * AjaxMiddleware
 *
 * Restricts a route to script-issued requests: a browser navigating to it
 * gets a 404, as though the route did not exist. Mirrors a Symfony routing
 * `condition` — a request that does not match simply finds nothing there.
 *
 * NOT a security control, and must never be used as one: every signal below
 * is client-supplied and trivially forged with a single header. It keeps data
 * endpoints out of the browser's address bar and out of crawlers and link
 * prefetchers; it does not decide who may read the data. That is
 * authorization's job, which a header cannot spoof.
 *
 * Three signals, because no single one covers real clients:
 *
 *   - `X-Requested-With: XMLHttpRequest` — jQuery and Axios send it; the
 *     native `fetch()` API never does, so on its own this would reject most
 *     modern callers, including this platform's own data tables.
 *   - `Sec-Fetch-Dest: empty` — set BY THE BROWSER rather than by the caller,
 *     and the one signal that separates fetch/XHR (`empty`) from a top-level
 *     navigation (`document`). This is what admits a plain `fetch()`.
 *   - `Accept: application/json` without `text/html` — an explicit request for
 *     data, covering non-browser callers such as curl, tests and API clients.
 *
 * Framework-agnostic: PSR-7/15 plus a PSR-17 response factory for the 404.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\HttpMiddleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AjaxMiddleware implements MiddlewareInterface
{
    /**
     * @param ResponseFactoryInterface $responses Creates the 404 for a non-script request
     */
    public function __construct(
        private readonly ResponseFactoryInterface $responses,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!self::isAjax($request)) {
            // Bodiless on purpose: a route that answers nothing should also explain nothing.
            return $this->responses->createResponse(404);
        }

        return $handler->handle($request);
    }

    /**
     * Whether the request was issued by script rather than by a navigation.
     *
     * Exposed so a handler can ask the same question without duplicating the
     * rules — and so the rules are testable on their own.
     *
     * @param ServerRequestInterface $request The request to inspect
     *
     * @return bool
     */
    public static function isAjax(ServerRequestInterface $request): bool
    {
        if (strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest') {
            return true;
        }

        // Browser-set, and the only reliable way to tell fetch/XHR from a navigation.
        if (strtolower($request->getHeaderLine('Sec-Fetch-Dest')) === 'empty') {
            return true;
        }

        $accept = strtolower($request->getHeaderLine('Accept'));

        return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
    }
}
