<?php

declare(strict_types=1);

/**
 * Thin PSR-18 client over the npm registry HTTP API.
 *
 * Backs binary download (package metadata) and discovery (search) — Bun has no search command, so
 * discovery uses the registry's search endpoint. Both honour the configured registry URL, so a
 * corporate mirror is a single setting.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Registry;

use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Exception\RegistryException;
use PHPdot\Container\Attribute\Singleton;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

#[Singleton]
final class NpmRegistryClient
{
    /**
     * Wire the registry client to its HTTP client, request factory, and config.
     *
     * @param ClientInterface $http
     * @param RequestFactoryInterface $requestFactory
     * @param BunConfig $config
     */
    public function __construct(
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly BunConfig $config,
    ) {}

    /**
     * The configured registry base URL. Lets dependent requests (e.g. tarball downloads) stay on the
     * same transport security as metadata requests.
     *
     * @return string
     */
    public function registryUrl(): string
    {
        return $this->config->registryUrl;
    }

    /**
     * Fetch the full registry document for a package (all versions and dist metadata).
     *
     *
     * @param string $package
     *
     * @throws RegistryException
     *
     * @return array<string, mixed>
     */
    public function packageDocument(string $package): array
    {
        return $this->getJson(rtrim($this->config->registryUrl, '/') . '/' . $package, $package);
    }

    /**
     * Search the registry: `GET /-/v1/search?text=&size=`.
     *
     *
     * @param string $term
     * @param int $limit
     *
     * @throws RegistryException
     *
     * @return list<SearchResult>
     */
    public function search(string $term, int $limit = 20): array
    {
        $query = http_build_query(['text' => $term, 'size' => $limit]);
        $data = $this->getJson(
            rtrim($this->config->registryUrl, '/') . '/-/v1/search?' . $query,
            sprintf('search "%s"', $term),
        );

        $objects = $data['objects'] ?? null;
        if (!is_array($objects)) {
            return [];
        }

        $results = [];
        foreach ($objects as $object) {
            if (!is_array($object)) {
                continue;
            }

            $package = $object['package'] ?? null;
            if (!is_array($package)) {
                continue;
            }

            $name = $package['name'] ?? null;
            if (!is_string($name)) {
                continue;
            }

            $version = $package['version'] ?? null;
            $description = $package['description'] ?? null;
            $scoreData = $object['score'] ?? null;
            $final = is_array($scoreData) ? ($scoreData['final'] ?? null) : null;

            $results[] = new SearchResult(
                $name,
                is_string($version) ? $version : '',
                is_string($description) ? $description : '',
                is_int($final) || is_float($final) ? (float) $final : 0.0,
            );
        }

        return $results;
    }

    /**
     * Fetches and decodes a JSON document from the registry, wrapping transport failures.
     *
     * @param string $url
     * @param string $context
     *
     * @throws RegistryException
     *
     * @return array<string, mixed>
     */
    private function getJson(string $url, string $context): array
    {
        $request = $this->requestFactory->createRequest('GET', $url);

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new RegistryException(
                sprintf('Registry request failed for %s: %s', $context, $e->getMessage()),
                0,
                $e,
            );
        }

        $status = $response->getStatusCode();
        if ($status >= 400) {
            throw new RegistryException(sprintf('Registry returned HTTP %d for %s', $status, $context));
        }

        $decoded = json_decode((string) $response->getBody(), true);
        if (!is_array($decoded)) {
            throw new RegistryException(sprintf('Invalid JSON from registry for %s', $context));
        }

        /**
         * @var array<string, mixed> $decoded
         */
        return $decoded;
    }
}
