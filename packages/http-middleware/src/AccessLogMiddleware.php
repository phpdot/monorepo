<?php

declare(strict_types=1);

/**
 * Access Log Middleware
 *
 * One line per request, Apache-style: whoever greps an access log for a
 * status code, an IP, or a path gets the same answer here — as a trace-
 * correlated line on the `http` channel, with the same fields stamped as
 * attributes on the root span so the span record itself is the machine-
 * readable access entry.
 *
 * Must sit OUTERMOST in the pipeline: it logs the response actually sent,
 * including the 500s an inner error handler synthesized. Anything that can
 * produce a response belongs inside it.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\HttpMiddleware;

use PHPdot\Contracts\Logs\TracerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final class AccessLogMiddleware implements MiddlewareInterface
{
    /**
     * Client statuses logged as notices — facts about the request, not
     * incidents — mirroring the error handler's mapping so access lines and
     * exception lines agree on severity.
     */
    private const ROUTINE_CLIENT_STATUSES = [404, 405, 410];

    public function __construct(
        private readonly TracerInterface $tracer,
        private readonly string $channel = 'http',
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startedAt = hrtime(true);

        try {
            $response = $handler->handle($request);
        } catch (Throwable $error) {
            $this->record($request, 500, null, $startedAt);

            throw $error;
        }

        $this->record($request, $response->getStatusCode(), $response->getBody()->getSize(), $startedAt);

        return $response;
    }

    /**
     * Stamp the Apache field set onto the active (root) span and emit the
     * access line. Called with the final status — 500 when the pipeline
     * itself blew up past the error handler, so every request that entered
     * gets exactly one line.
     *
     * @param int $startedAt Monotonic hrtime mark taken when the request entered.
     */
    private function record(ServerRequestInterface $request, int $status, null|int $bytes, int $startedAt): void
    {
        $path = $request->getUri()->getPath();
        $query = $request->getUri()->getQuery();
        $agent = $request->getHeaderLine('User-Agent');
        $referer = $request->getHeaderLine('Referer');
        $client = $this->clientAddress($request);

        /**
         * One field set, stamped on the span and carried in the log context, so the two
         * views of a request cannot drift apart. Empty optionals are omitted rather than
         * written blank. The duration is namespaced because the span record already
         * carries its own `duration_ms` for a wider span of work.
         *
         * @var array<string, string|int|float|bool> $fields
         */
        $fields = [
            'http.method' => $request->getMethod(),
            'url.path' => $path === '' ? '/' : $path,
            'http.status_code' => $status,
            'http.server.duration_ms' => round((hrtime(true) - $startedAt) / 1e6, 3),
        ];

        if ($query !== '') {
            $fields['url.query'] = $query;
        }

        if ($client !== '') {
            $fields['client.address'] = $client;
        }

        if ($agent !== '') {
            $fields['user.agent'] = $agent;
        }

        if ($referer !== '') {
            $fields['http.referer'] = $referer;
        }

        if ($bytes !== null) {
            $fields['http.response.bytes'] = $bytes;
        }

        $span = $this->tracer->current();

        foreach ($fields as $key => $value) {
            $span->setAttribute($key, $value);
        }

        $message = sprintf('%s %s %d', $request->getMethod(), $request->getRequestTarget(), $status);
        $channel = $this->tracer->channel($this->channel);

        match ($this->level($status)) {
            'error' => $channel->error($message, $fields),
            'warning' => $channel->warning($message, $fields),
            'notice' => $channel->notice($message, $fields),
            default => $channel->info($message, $fields),
        };
    }

    /**
     * The tracer's own vocabulary — this package carries no PSR-3 dependency,
     * the same call the error handler makes.
     *
     * @return 'info'|'notice'|'warning'|'error'
     */
    private function level(int $status): string
    {
        return match (true) {
            $status >= 500 => 'error',
            in_array($status, self::ROUTINE_CLIENT_STATUSES, true) => 'notice',
            $status >= 400 => 'warning',
            default => 'info',
        };
    }

    /**
     * The connected peer's address. Forwarded-proxy resolution (mod_remoteip
     * territory) is deliberately absent until a trusted-proxies list exists —
     * trusting X-Forwarded-For unconditionally spoofs the access log.
     */
    private function clientAddress(ServerRequestInterface $request): string
    {
        $remote = $request->getServerParams()['REMOTE_ADDR'] ?? '';

        return is_string($remote) ? $remote : '';
    }
}
