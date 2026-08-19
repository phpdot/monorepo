<?php

declare(strict_types=1);

/**
 * Per-package dependency-hygiene gate for the hybrid monorepo/mirror model.
 *
 * The monorepo install masks manifest gaps: a package's src can import a class
 * that only the root composer.json provides, which is invisible here but fatal
 * in the standalone public mirror. This script re-derives, for every package,
 * the set of packages its src actually imports and fails when one is missing
 * from that package's own require block.
 *
 * What it checks (deliberately one-directional — only the mirror-fatal
 * direction):
 *  - every `use` import in packages/<name>/src resolving to a phpdot package or
 *    an installed vendor package must be declared in that package's require;
 *  - `Swoole\*` imports require ext-swoole, `MongoDB\Driver\*` ext-mongodb,
 *    `Composer\*` composer-runtime-api (or composer/composer);
 *  - a curated set of extension function families (mb_*, curl_*, openssl_*,
 *    image*, simplexml_*, finfo_*) must be backed by the matching ext-* entry.
 *
 * Sanctioned exceptions, mirroring the tree's documented soft-dependency
 * doctrine: PHPdot\Container\Attribute\* imports are inert until a phpdot
 * application reflects them, so require-dev (or require) suffices there; a
 * phpdot package imported only by an optional bridge class passes when it sits
 * in require-dev AND suggest (totp's QrCodeBridge form); an extension-class
 * import passes when the ext-* entry is in require OR suggest (console's
 * extension_loaded-guarded Swoole helpers). Not checked: unused declared
 * dependencies, FQCN references without imports (the class_exists-guarded
 * optional-integration idiom relies on those), and tests/ (dev installs always
 * run from a full monorepo checkout).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

$root = dirname(__DIR__, 2);

/**
 * Longest-prefix namespace map: PSR-4 prefix => providing package names.
 *
 * A prefix can have several owners (psr/http-server-handler and
 * psr/http-server-middleware both claim Psr\Http\Server\), so each entry is a
 * list and an import is satisfied by any declared owner.
 *
 * @return array<string, list<string>>
 */
function buildPrefixMap(string $root): array
{
    $map = [];

    foreach (glob("$root/packages/*/composer.json") ?: [] as $manifest) {
        $data = json_decode((string) file_get_contents($manifest), true, 8, JSON_THROW_ON_ERROR);
        foreach ($data['autoload']['psr-4'] ?? [] as $prefix => $dir) {
            $map[$prefix][] = $data['name'];
        }
    }

    $installed = json_decode(
        (string) file_get_contents("$root/vendor/composer/installed.json"),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    foreach ($installed['packages'] ?? [] as $pkg) {
        foreach ($pkg['autoload']['psr-4'] ?? [] as $prefix => $dir) {
            $map[$prefix][] = $pkg['name'];
        }
        foreach ($pkg['autoload']['psr-0'] ?? [] as $prefix => $dir) {
            $map[$prefix][] = $pkg['name'];
        }
    }

    uksort($map, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

    return $map;
}

/**
 * Expand every `use` statement (plain, function/const, and group syntax) in a
 * PHP source file into fully-qualified imported names.
 *
 * @return list<string>
 */
function importsOf(string $file): array
{
    $src = (string) file_get_contents($file);
    $imports = [];

    preg_match_all(
        '/^use\s+(?:function\s+|const\s+)?([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)(?:\s*\{([^}]+)\})?\s*(?:as\s+\w+)?\s*;/m',
        $src,
        $matches,
        PREG_SET_ORDER,
    );

    foreach ($matches as $m) {
        if (isset($m[2])) {
            foreach (explode(',', $m[2]) as $leaf) {
                $leaf = trim($leaf);
                $leaf = (string) preg_replace('/^(?:function|const)\s+/', '', $leaf);
                $leaf = (string) preg_replace('/\s+as\s+\w+$/', '', $leaf);
                $imports[] = rtrim($m[1], '\\') . '\\' . $leaf;
            }
        } else {
            $imports[] = $m[1];
        }
    }

    return $imports;
}

/**
 * Curated map of extension function-call families to the ext-* package that
 * must back them. Matches bare and \-prefixed calls; skips methods/statics.
 *
 * @return list<string> ext package names used by the file
 */
function extensionsOf(string $file): array
{
    $families = [
        'mb_' => 'ext-mbstring',
        'curl_' => 'ext-curl',
        'openssl_' => 'ext-openssl',
        'image(?:create|destroy|s[xy]|png|jpeg|webp|gif|bmp|avif|color|copy|scale|crop|rotate|fill|setpixel|line|string|ttftext)' => 'ext-gd',
        'simplexml_' => 'ext-simplexml',
        'finfo_' => 'ext-fileinfo',
    ];
    $src = (string) file_get_contents($file);
    $used = [];

    foreach ($families as $needle => $ext) {
        if (preg_match('/(?<![\w$>:\'"])\\\\?' . $needle . '[a-z_0-9]*\s*\(/', $src) === 1) {
            $used[] = $ext;
        }
    }

    return $used;
}

$prefixMap = buildPrefixMap($root);
$failures = [];

foreach (glob("$root/packages/*/composer.json") ?: [] as $manifest) {
    $dir = dirname($manifest);
    $package = basename($dir);
    $data = json_decode((string) file_get_contents($manifest), true, 8, JSON_THROW_ON_ERROR);
    $self = $data['name'];
    $require = array_keys($data['require'] ?? []);
    $requireDev = array_keys($data['require-dev'] ?? []);
    $suggest = array_keys($data['suggest'] ?? []);

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$dir/src", FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $rel = substr($path, strlen($root) + 1);

        foreach (importsOf($path) as $import) {
            if (!str_contains($import, '\\')) {
                continue;
            }
            if (str_starts_with($import, 'Swoole\\')) {
                if (!in_array('ext-swoole', [...$require, ...$suggest], true)) {
                    $failures[] = "$rel imports $import but $package has ext-swoole in neither require nor suggest";
                }
                continue;
            }
            if (str_starts_with($import, 'MongoDB\\Driver\\')) {
                if (!in_array('ext-mongodb', [...$require, ...$suggest], true)) {
                    $failures[] = "$rel imports $import but $package has ext-mongodb in neither require nor suggest";
                }
                continue;
            }
            if (str_starts_with($import, 'Composer\\')) {
                if (!in_array('composer-runtime-api', $require, true)
                    && !in_array('composer/composer', [...$require, ...$requireDev], true)
                ) {
                    $failures[] = "$rel imports $import but $package does not require composer-runtime-api";
                }
                continue;
            }
            if (str_starts_with($import, 'PHPdot\\Container\\Attribute\\')) {
                if ($self !== 'phpdot/container'
                    && !in_array('phpdot/container', [...$require, ...$requireDev], true)
                ) {
                    $failures[] = "$rel imports $import but $package has phpdot/container in neither require nor require-dev";
                }
                continue;
            }

            foreach ($prefixMap as $prefix => $owners) {
                if (!str_starts_with($import . '\\', $prefix)) {
                    continue;
                }
                $satisfied = in_array($self, $owners, true)
                    || array_intersect($owners, $require) !== []
                    || array_intersect($owners, array_intersect($requireDev, $suggest)) !== [];
                if (!$satisfied) {
                    $failures[] = "$rel imports $import but $package does not require " . implode('|', $owners);
                }
                break;
            }
        }

        foreach (extensionsOf($path) as $ext) {
            if (!in_array($ext, $require, true)) {
                $failures[] = "$rel calls $ext functions but $package does not require $ext";
            }
        }
    }
}

$failures = array_values(array_unique($failures));
if ($failures !== []) {
    fwrite(STDERR, "dependency hygiene: src imports not covered by the package's own require:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "  $failure\n");
    }
    exit(1);
}

echo "dependency hygiene: every package's src imports are covered by its own require block\n";
