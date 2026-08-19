<?php

declare(strict_types=1);

/**
 * README Requirements-table drift gate.
 *
 * Every package README carries a Requirements table that mirrors its
 * composer.json require block — the table is what a mirror consumer reads
 * before installing, so a stale row misdocuments the package's real
 * platform contract. This script parses each table and fails when a row's
 * constraint disagrees with composer.json, when a require entry is missing
 * from the table, or when the table names a dependency the manifest no
 * longer requires.
 *
 * Prose rows that name no real package (e.g. env's "Composer dependencies —
 * none") are ignored, as is the "PHP" row's spelling; constraints compare
 * after normalizing backticks, escaped pipes, and surrounding whitespace.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

$root = dirname(__DIR__, 2);
$failures = [];

/**
 * Normalize a table cell or constraint for comparison.
 *
 * @param string $value
 *
 * @return string
 */
function normalize(string $value): string
{
    $value = str_replace(['`', '\\|\\|', '\\|'], ['', '||', '|'], $value);
    $value = (string) preg_replace('/\s+—.*$/u', '', $value);
    $value = (string) preg_replace('/\s+/', ' ', $value);

    return trim($value);
}

foreach (glob("$root/packages/*/composer.json") ?: [] as $manifest) {
    $dir = dirname($manifest);
    $package = basename($dir);
    $readme = "$dir/README.md";

    if (!is_file($readme)) {
        $failures[] = "$package: README.md missing";
        continue;
    }

    $data = json_decode((string) file_get_contents($manifest), true, 8, JSON_THROW_ON_ERROR);
    $require = $data['require'] ?? [];
    $suggest = $data['suggest'] ?? [];
    $contents = (string) file_get_contents($readme);

    if (preg_match('/## Requirements\n(.*?)(\n## |\z)/s', $contents, $m) !== 1) {
        $failures[] = "$package: README has no Requirements section";
        continue;
    }

    $documented = [];
    foreach (explode("\n", $m[1]) as $line) {
        if (preg_match('/^\|\s*(.+?)\s*\|\s*(.+?)\s*\|$/', $line, $row) !== 1) {
            continue;
        }
        $name = normalize($row[1]);
        if ($name === 'Requirement' || str_starts_with($name, '--')) {
            continue;
        }
        if (strcasecmp($name, 'php') === 0) {
            $name = 'php';
        }
        if (!str_contains($name, '/') && !str_starts_with($name, 'ext-') && !str_starts_with($name, 'composer-') && $name !== 'php') {
            continue;
        }
        $documented[$name] = normalize($row[2]);
    }

    foreach ($require as $dep => $constraint) {
        if (!isset($documented[$dep])) {
            $failures[] = "$package: require has $dep \"$constraint\" but the README table lacks it";
            continue;
        }
        if (str_replace(' ', '', normalize($documented[$dep])) !== str_replace(' ', '', normalize($constraint))) {
            $failures[] = "$package: README documents $dep \"{$documented[$dep]}\" but composer.json requires \"$constraint\"";
        }
    }

    foreach (array_keys($documented) as $dep) {
        if (!array_key_exists($dep, $require) && !array_key_exists($dep, $suggest)) {
            $failures[] = "$package: README table documents $dep, which is in neither require nor suggest";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "README Requirements tables out of sync with composer.json:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "  $failure\n");
    }
    exit(1);
}

echo "README Requirements tables match every package's composer.json require block\n";
