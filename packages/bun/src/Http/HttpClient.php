<?php

declare(strict_types=1);

/**
 * Default PSR-18 client + PSR-17 request factory for registry access and binary download.
 *
 * Wraps symfony/http-client (redirect-following, TLS, streaming) behind the PSR interfaces and
 * declares itself as the default binding via #[Binds] so the manifest scanner auto-wires
 * {@see \PHPdot\Bun\Registry\NpmRegistryClient} and {@see \PHPdot\Bun\Runtime\BinaryDownloader}
 * with zero application config. An app may override either binding with its own client.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;
use Symfony\Component\HttpClient\Psr18Client;

#[Singleton]
#[Binds(ClientInterface::class)]
#[Binds(RequestFactoryInterface::class)]
final class HttpClient implements ClientInterface, RequestFactoryInterface
{
    /**
     * Idle (inactivity) timeout in seconds — a stalled transfer fails instead of hanging forever.
     */
    private const float IDLE_TIMEOUT = 30.0;

    private readonly Psr18Client $client;

    /**
     * Build a PSR-18 client backed by Symfony HttpClient and nyholm PSR-17 factories.
     */
    public function __construct()
    {
        $factory = new Psr17Factory();
        $this->client = new Psr18Client(
            SymfonyHttpClient::create(['max_duration' => 0, 'timeout' => self::IDLE_TIMEOUT]),
            $factory,
            $factory,
        );
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->sendRequest($request);
    }

    /**
     * @param string|UriInterface $uri
     */
    public function createRequest(string $method, $uri): RequestInterface
    {
        return $this->client->createRequest($method, $uri);
    }
}
