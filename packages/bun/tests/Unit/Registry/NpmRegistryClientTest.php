<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit\Registry;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Registry\NpmRegistryClient;
use PHPdot\Bun\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class NpmRegistryClientTest extends TestCase
{
    public function testSearchMapsObjectsToResults(): void
    {
        $http = new FakeHttpClient();
        $http->map($this->searchUrl('https://registry.npmjs.org', 'chart', 20), (string) json_encode([
            'objects' => [
                ['package' => ['name' => 'chart.js', 'version' => '4.4.0', 'description' => 'Simple HTML5 charts'], 'score' => ['final' => 0.95]],
                ['package' => ['name' => 'chartist', 'version' => '1.3.0', 'description' => 'Responsive charts'], 'score' => ['final' => 0.8]],
            ],
            'total' => 2,
        ]));

        $results = $this->client($http)->search('chart', 20);

        self::assertCount(2, $results);
        self::assertSame('chart.js', $results[0]->name);
        self::assertSame('4.4.0', $results[0]->version);
        self::assertSame('Simple HTML5 charts', $results[0]->description);
        self::assertSame(0.95, $results[0]->score);
        self::assertSame('chartist', $results[1]->name);
    }

    public function testSearchHandlesZeroResults(): void
    {
        $http = new FakeHttpClient();
        $http->map($this->searchUrl('https://registry.npmjs.org', 'no-such-pkg-xyz', 20), (string) json_encode(['objects' => [], 'total' => 0]));

        self::assertSame([], $this->client($http)->search('no-such-pkg-xyz', 20));
    }

    public function testSearchRespectsLimit(): void
    {
        $http = new FakeHttpClient();
        $url = $this->searchUrl('https://registry.npmjs.org', 'chart', 5);
        $http->map($url, (string) json_encode(['objects' => [['package' => ['name' => 'chart.js', 'version' => '4.4.0']]], 'total' => 1]));

        $results = $this->client($http)->search('chart', 5);

        self::assertCount(1, $results);
        self::assertSame(1, $http->hits[$url] ?? 0, 'the size=5 endpoint should have been requested');
    }

    public function testSearchUsesConfiguredMirror(): void
    {
        $mirror = 'https://npm.mycorp.test';
        $http = new FakeHttpClient();
        $http->map($this->searchUrl($mirror, 'chart', 20), (string) json_encode(['objects' => [['package' => ['name' => 'chart.js', 'version' => '4.4.0']]], 'total' => 1]));

        $results = $this->client($http, $mirror)->search('chart', 20);

        self::assertCount(1, $results);
        self::assertSame('chart.js', $results[0]->name);
    }

    public function testSearchToleratesMissingFields(): void
    {
        $http = new FakeHttpClient();
        $http->map($this->searchUrl('https://registry.npmjs.org', 'x', 20), (string) json_encode([
            'objects' => [['package' => ['name' => 'minimal']]],
        ]));

        $results = $this->client($http)->search('x', 20);

        self::assertSame('minimal', $results[0]->name);
        self::assertSame('', $results[0]->version);
        self::assertSame('', $results[0]->description);
        self::assertSame(0.0, $results[0]->score);
    }

    private function client(FakeHttpClient $http, string $registryUrl = 'https://registry.npmjs.org'): NpmRegistryClient
    {
        $factory = new Psr17Factory();

        return new NpmRegistryClient($http, $factory, new BunConfig(registryUrl: $registryUrl));
    }

    private function searchUrl(string $registryUrl, string $term, int $limit): string
    {
        return $registryUrl . '/-/v1/search?' . http_build_query(['text' => $term, 'size' => $limit]);
    }
}
