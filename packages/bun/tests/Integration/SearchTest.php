<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Integration;

use PHPdot\Bun\Command\SearchCommand;
use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Http\HttpClient;
use PHPdot\Bun\Registry\NpmRegistryClient;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Slice 3 acceptance: a real npm registry search returns results rendered as a table.
 */
#[Group('integration')]
final class SearchTest extends TestCase
{
    public function testRealSearchReturnsResults(): void
    {
        if (getenv('BUN_LIVE') !== '1') {
            self::markTestSkipped('Live Bun integration test — set BUN_LIVE=1 to run it (downloads the real Bun binary over the network).');
        }
        if (!class_exists(HttpClient::class)) {
            self::markTestSkipped('symfony/http-client is required for the integration test');
        }

        $http = new HttpClient();
        $registry = new NpmRegistryClient($http, $http, new BunConfig());

        $results = $registry->search('chart', 5);
        self::assertNotEmpty($results, 'a search for "chart" should return packages');
        self::assertLessThanOrEqual(5, count($results), '--limit should be respected');

        $tester = new CommandTester(new SearchCommand($registry));
        $tester->execute(['term' => 'chart', '--limit' => '5']);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        self::assertStringContainsString('Version', $display);
        self::assertStringContainsStringIgnoringCase('chart', $display);
    }
}
