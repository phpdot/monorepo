<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Integration;

use PHPdot\Bun\Command\BuildCommand;
use PHPdot\Bun\Http\HttpClient;
use PHPdot\Bun\Tests\Support\IntegrationBun;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Exercises the bun:build CLI command end-to-end with a real Bun binary (not the fake): it maps the
 * console flags and actually produces a bundle. Absolute paths keep it independent of the cwd.
 */
#[Group('integration')]
final class BuildCommandIntegrationTest extends TestCase
{
    private string $dir;
    private string $cwd;

    protected function setUp(): void
    {
        if (getenv('BUN_LIVE') !== '1') {
            self::markTestSkipped('Live Bun integration test — set BUN_LIVE=1 to run it (downloads the real Bun binary over the network).');
        }
        if (!class_exists(HttpClient::class)) {
            self::markTestSkipped('symfony/http-client is required for the integration test');
        }
        $this->dir = sys_get_temp_dir() . '/phpdot-bun-cmd-' . uniqid();
        mkdir($this->dir, 0755, true);
        // The command runs bun with cwd=null, so the throwaway metafile resolves against the process
        // cwd; run inside the fixture dir so it lands in temp instead of the repo.
        $this->cwd = (string) getcwd();
        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (!isset($this->dir)) {
            return;
        }
        chdir($this->cwd);
        $this->deleteTree($this->dir);
    }

    public function testBuildCommandBundlesAFixture(): void
    {
        file_put_contents($this->dir . '/app.ts', "export const answer = 42;\nconsole.log(answer);\n");

        $tester = new CommandTester(new BuildCommand(IntegrationBun::create()));
        $tester->execute([
            'entry' => [$this->dir . '/app.ts'],
            '--out-dir' => $this->dir . '/dist',
            '--minify' => true,
        ]);

        $tester->assertCommandIsSuccessful();
        self::assertNotEmpty(
            array_filter((array) glob($this->dir . '/dist/*.js'), is_string(...)),
            'bun:build should emit a bundle',
        );
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        /** @var list<string> $entries */
        $entries = array_diff((array) scandir($path), ['.', '..']);
        foreach ($entries as $entry) {
            $full = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($full) && !is_link($full) ? $this->deleteTree($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
