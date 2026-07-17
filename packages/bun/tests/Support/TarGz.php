<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Support;

/**
 * Builds minimal gzipped tar archives (single ustar file entry) for extraction tests, mirroring
 * the layout of a Bun npm tarball (`package/bin/bun`).
 */
final class TarGz
{
    /**
     * @param array<string, string> $files map of internal path => contents
     */
    public static function build(array $files): string
    {
        $tar = '';
        foreach ($files as $path => $contents) {
            $tar .= self::header($path, strlen($contents));
            $tar .= $contents;
            $padding = strlen($contents) % 512;
            if ($padding !== 0) {
                $tar .= str_repeat("\0", 512 - $padding);
            }
        }
        $tar .= str_repeat("\0", 1024); // two trailing zero blocks

        return (string) gzencode($tar);
    }

    /**
     * The sha512 integrity string (`sha512-<base64>`) npm publishes for a tarball's bytes.
     */
    public static function integrity(string $tgz): string
    {
        return 'sha512-' . base64_encode(hash('sha512', $tgz, true));
    }

    private static function header(string $name, int $size): string
    {
        $header = str_repeat("\0", 512);
        $header = substr_replace($header, $name, 0, strlen($name));
        $header = substr_replace($header, sprintf('%011o', $size) . "\0", 124, 12);
        $header = substr_replace($header, '0', 156, 1); // typeflag: regular file

        return $header;
    }
}
