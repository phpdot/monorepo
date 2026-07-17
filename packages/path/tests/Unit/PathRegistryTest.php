<?php

declare(strict_types=1);

namespace PHPdot\Path\Tests\Unit;

use PHPdot\Config\Configuration;
use PHPdot\Path\Exception\PathNotMapped;
use PHPdot\Path\PathRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PathRegistryTest extends TestCase
{
    private function registry(): PathRegistry
    {
        // Empty base in the fixture → the root is auto-detected from Composer.
        return new PathRegistry(new Configuration(__DIR__ . '/../Fixtures/config'));
    }

    #[Test]
    public function it_resolves_base_relative_built_ins(): void
    {
        $registry = $this->registry();
        $base = $registry->base();

        self::assertSame($base . '/config', $registry->config());
        self::assertSame($base . '/vendor', $registry->vendor());
        self::assertSame($base . '/public', $registry->public());
        self::assertSame($base . '/protected', $registry->protected());
    }

    #[Test]
    public function get_resolves_built_ins_too(): void
    {
        $registry = $this->registry();

        self::assertSame($registry->base() . '/config', $registry->get('config'));
        self::assertSame($registry->base(), $registry->get('base'));
    }

    #[Test]
    public function it_resolves_custom_names_that_reference_built_ins(): void
    {
        $registry = $this->registry();
        $base = $registry->base();

        self::assertSame($base . '/protected/uploads', $registry->get('uploads'));
        self::assertSame($base . '/var/log', $registry->get('logs'));
    }

    #[Test]
    public function has_reports_whether_a_name_is_mapped(): void
    {
        $registry = $this->registry();

        self::assertTrue($registry->has('uploads'));
        self::assertFalse($registry->has('nope'));
    }

    #[Test]
    public function get_throws_for_an_unknown_name(): void
    {
        $this->expectException(PathNotMapped::class);

        $this->registry()->get('nope');
    }

    #[Test]
    public function it_uses_a_prefilled_absolute_base_as_is(): void
    {
        $config = new Configuration(__DIR__ . '/../Fixtures/config-prefilled');

        // No injected root: the absolute `base` from config is used directly.
        $registry = new PathRegistry($config);
        $base = $registry->base();

        self::assertSame(dirname(__DIR__, 2), $base);
        self::assertSame($base . '/config', $registry->config());
        self::assertSame($base . '/protected/uploads', $registry->get('uploads'));
    }

    #[Test]
    public function it_reads_config_and_vendor_dirs_from_composer_json(): void
    {
        $root = sys_get_temp_dir() . '/phpdot-path-cd-' . bin2hex(random_bytes(6));
        mkdir($root . '/cfg', 0o777, true);
        file_put_contents($root . '/composer.json', (string) json_encode([
            'config' => ['vendor-dir' => 'protected/vendor'],
            'extra' => ['phpdot' => ['config-dir' => 'cfg']],
        ]));
        file_put_contents($root . '/cfg/path.php', "<?php\n\nreturn ['base' => " . var_export($root, true) . "];\n");

        $registry = new PathRegistry(new Configuration($root . '/cfg'));
        $base = $registry->base();

        self::assertSame($base . '/protected/vendor', $registry->vendor());
        self::assertSame($base . '/cfg', $registry->config());

        unlink($root . '/cfg/path.php');
        unlink($root . '/composer.json');
        rmdir($root . '/cfg');
        rmdir($root);
    }
}
