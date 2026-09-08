<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Integration;

use PHPdot\Bun\Config\BunConfig;
use PHPdot\Bun\Http\HttpClient;
use PHPdot\Bun\Resources\AssetPipeline;
use PHPdot\Bun\Resources\Recipe;
use PHPdot\Bun\Tests\Support\IntegrationBun;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * A recipe script step end to end with a real Bun binary: an authored extractor is bridged into
 * home/build, runs through `bun run` from home, and its generated css rides the entry through the
 * `@build/*` alias into the hashed outputs and the manifest — the webfont pipeline in miniature.
 */
#[Group('integration')]
final class ScriptStepTest extends TestCase
{
    private string $root;

    private string $home;

    private string $resources;

    private string $output;

    protected function setUp(): void
    {
        if (getenv('BUN_LIVE') !== '1') {
            self::markTestSkipped('Live Bun integration test — set BUN_LIVE=1 to run it (downloads the real Bun binary over the network).');
        }
        if (!class_exists(HttpClient::class)) {
            self::markTestSkipped('symfony/http-client is required for the integration test');
        }
        $this->root = sys_get_temp_dir() . '/phpdot-bun-scriptstep-' . uniqid();
        $this->home = $this->root . '/resources/.bun';
        $this->resources = $this->root . '/resources';
        $this->output = $this->root . '/public/build';
        mkdir($this->resources, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (!isset($this->root)) {
            return;
        }

        foreach ([$this->output, $this->resources, $this->home, $this->root] as $dir) {
            $this->deleteTree($dir);
        }
    }

    public function testAScriptStepsGeneratedCssRidesTheEntryIntoTheManifest(): void
    {
        $extractor = "import { writeFileSync } from 'node:fs';\nwriteFileSync('build/app.icons.css', '.webfont-icon { font-family: app-icons; }\\n');\n";
        file_put_contents($this->resources . '/platform.ts', "import '@build/app.icons.css';\nconsole.log('ok');\n");

        $bun = IntegrationBun::create($this->resources);
        $config = new BunConfig(resourcesDir: $this->resources, outputDir: $this->output);

        $recipe = new Recipe($this->home);
        $recipe->run($recipe->bridge('extract-icons.mjs', $extractor));
        $recipe->entries($this->resources . '/platform.ts');

        $exit = (new AssetPipeline($bun, $config))->build($recipe);

        self::assertSame(0, $exit, 'the script step and the bundle must both succeed');

        $manifest = (string) file_get_contents($this->output . '/manifest.json');
        self::assertStringContainsString('platform.ts', $manifest, 'the manifest keys the authored entry');

        $css = array_values(array_filter((array) glob($this->output . '/css/*.css'), is_string(...)));
        self::assertCount(1, $css, 'the script-generated css rides the entry');
        self::assertStringContainsString('webfont-icon', (string) file_get_contents($css[0]));
        self::assertStringContainsString('@charset', (string) file_get_contents($css[0]), 'built css carries the charset stamp');
    }

    public function testAFreshCloneWithACommittedManifestReinstallsItsDependencies(): void
    {
        $bun = IntegrationBun::create($this->resources);
        $config = new BunConfig(resourcesDir: $this->resources, outputDir: $this->output);

        $seed = $bun->install(['nanoid']);
        self::assertSame(0, $seed, 'seeding the committed manifest');
        self::assertDirectoryExists($this->home . '/node_modules/nanoid');

        $this->deleteTree($this->home . '/node_modules');
        file_put_contents($this->resources . '/platform.ts', "console.log('ok');\n");

        $recipe = new Recipe($this->home);
        $recipe->entries($this->resources . '/platform.ts');

        $exit = (new AssetPipeline($bun, $config))->build($recipe);

        self::assertSame(0, $exit, 'the repair installs and the build succeeds');
        self::assertDirectoryExists($this->home . '/node_modules/nanoid', 'bun install restored the committed dependencies');
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
