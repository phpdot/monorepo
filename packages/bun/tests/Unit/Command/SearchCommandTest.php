<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit\Command;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Bun\Command\SearchCommand;
use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Registry\NpmRegistryClient;
use PHPdot\Bun\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SearchCommandTest extends TestCase
{
    public function testRendersResultsTable(): void
    {
        $http = new FakeHttpClient();
        $http->map(
            'https://registry.npmjs.org/-/v1/search?' . http_build_query(['text' => 'chart', 'size' => 20]),
            (string) json_encode([
                'objects' => [
                    ['package' => ['name' => 'chart.js', 'version' => '4.4.0', 'description' => 'Simple HTML5 charts'], 'score' => ['final' => 0.95]],
                ],
            ]),
        );

        $tester = new CommandTester(new SearchCommand($this->registry($http)));
        $tester->execute(['term' => 'chart']);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        self::assertStringContainsString('chart.js', $display);
        self::assertStringContainsString('4.4.0', $display);
        self::assertStringContainsString('Version', $display);
    }

    public function testReportsNoResults(): void
    {
        $http = new FakeHttpClient();
        $http->map(
            'https://registry.npmjs.org/-/v1/search?' . http_build_query(['text' => 'nothinghere', 'size' => 20]),
            (string) json_encode(['objects' => []]),
        );

        $tester = new CommandTester(new SearchCommand($this->registry($http)));
        $tester->execute(['term' => 'nothinghere']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('No packages found', $tester->getDisplay());
    }

    public function testRespectsLimitOption(): void
    {
        $http = new FakeHttpClient();
        $url = 'https://registry.npmjs.org/-/v1/search?' . http_build_query(['text' => 'chart', 'size' => 3]);
        $http->map($url, (string) json_encode(['objects' => [['package' => ['name' => 'chart.js', 'version' => '4.4.0']]]]));

        $tester = new CommandTester(new SearchCommand($this->registry($http)));
        $tester->execute(['term' => 'chart', '--limit' => '3']);

        $tester->assertCommandIsSuccessful();
        self::assertSame(1, $http->hits[$url] ?? 0);
    }

    private function registry(FakeHttpClient $http): NpmRegistryClient
    {
        $factory = new Psr17Factory();

        return new NpmRegistryClient($http, $factory, new BunConfig());
    }
}
