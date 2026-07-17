<?php

declare(strict_types=1);

/**
 * Downloads a Bun platform binary from the npm registry, verifies its integrity and extracts it.
 *
 * The Bun binary ships as an npm package whose tarball contains `package/bin/bun` (or `bun.exe`).
 * The whole pipeline streams: download to a temp file, sha512-verify the file, then stream the
 * single binary entry out of the gzipped tar — never loading the ~86 MB binary fully into memory.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Runtime;

use PHPdot\Bun\Exception\BinaryDownloadException;
use PHPdot\Bun\Registry\NpmRegistryClient;
use PHPdot\Container\Attribute\Singleton;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

#[Singleton]
final class BinaryDownloader
{
    private const int CHUNK = 65536;

    /**
     * Wire the downloader to its HTTP client, request factory, and npm registry client.
     *
     * @param ClientInterface $http
     * @param RequestFactoryInterface $requestFactory
     * @param NpmRegistryClient $registry
     */
    public function __construct(
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly NpmRegistryClient $registry,
    ) {}

    /**
     * Download $npmPackage@$version and extract its binary to $destination.
     *
     * @param string $binaryFilename
     * @param string $npmPackage
     * @param string $version
     * @param string $destination
     *
     * @throws BinaryDownloadException
     *
     * @return void
     */
    public function download(string $npmPackage, string $version, string $destination, string $binaryFilename): void
    {
        $doc = $this->registry->packageDocument($npmPackage);
        [$tarballUrl, $integrity] = $this->resolveDist($doc, $npmPackage, $version);

        $tmp = $this->downloadToTemp($tarballUrl);
        try {
            $this->verifyIntegrity($tmp, $integrity);
            $this->extractBinary($tmp, 'package/bin/' . $binaryFilename, $destination);
        } finally {
            @unlink($tmp);
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($destination, 0755);
        }
    }

    /**
     * Pulls the tarball URL and sha512 integrity string for the version out of the registry document.
     *
     * @param array<string, mixed> $doc
     * @param string $package
     * @param string $version
     *
     * @throws BinaryDownloadException
     *
     * @return array{0: string, 1: string} tarball URL and sha512 integrity string
     */
    private function resolveDist(array $doc, string $package, string $version): array
    {
        $versions = $doc['versions'] ?? null;
        if (!is_array($versions)) {
            throw new BinaryDownloadException(sprintf('No versions listed for %s', $package));
        }

        $entry = $versions[$version] ?? null;
        if (!is_array($entry)) {
            throw new BinaryDownloadException(sprintf('Version %s not found for %s', $version, $package));
        }

        $dist = $entry['dist'] ?? null;
        if (!is_array($dist)) {
            throw new BinaryDownloadException(sprintf('No dist metadata for %s@%s', $package, $version));
        }

        $tarball = $dist['tarball'] ?? null;
        $integrity = $dist['integrity'] ?? null;
        if (!is_string($tarball) || !is_string($integrity)) {
            throw new BinaryDownloadException(sprintf('Malformed dist metadata for %s@%s', $package, $version));
        }

        $this->assertSafeTarballUrl($tarball, $package, $version);

        return [$tarball, $integrity];
    }

    /**
     * Guard the transport of the tarball URL taken from the registry document: it must be HTTP(S),
     * and it must not downgrade to plain HTTP when the configured registry is HTTPS. Integrity still
     * verifies the bytes; this stops a doctored document from moving the fetch onto an insecure
     * channel.
     *
     * @param string $tarball
     * @param string $package
     * @param string $version
     *
     * @throws BinaryDownloadException
     *
     * @return void
     */
    private function assertSafeTarballUrl(string $tarball, string $package, string $version): void
    {
        $scheme = parse_url($tarball, PHP_URL_SCHEME);
        $scheme = is_string($scheme) ? strtolower($scheme) : '';
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new BinaryDownloadException(sprintf('Refusing non-HTTP(S) tarball URL for %s@%s', $package, $version));
        }

        $registryScheme = parse_url($this->registry->registryUrl(), PHP_URL_SCHEME);
        $registryScheme = is_string($registryScheme) ? strtolower($registryScheme) : '';
        if ($registryScheme === 'https' && $scheme !== 'https') {
            throw new BinaryDownloadException(sprintf(
                'Refusing insecure (non-HTTPS) tarball for %s@%s from an HTTPS registry',
                $package,
                $version,
            ));
        }
    }

    /**
     * Streams the tarball at the URL to a temporary file and returns its path.
     *
     * @param string $url
     *
     * @throws BinaryDownloadException
     *
     * @return string
     */
    private function downloadToTemp(string $url): string
    {
        $request = $this->requestFactory->createRequest('GET', $url);

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new BinaryDownloadException(sprintf('Failed to download %s: %s', $url, $e->getMessage()), 0, $e);
        }

        if ($response->getStatusCode() >= 400) {
            throw new BinaryDownloadException(sprintf('HTTP %d downloading %s', $response->getStatusCode(), $url));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'bun-');
        if ($tmp === false) {
            throw new BinaryDownloadException('Unable to create a temporary file for download');
        }

        try {
            $out = fopen($tmp, 'wb');
            if ($out === false) {
                throw new BinaryDownloadException(sprintf('Unable to open temporary file: %s', $tmp));
            }

            $body = $response->getBody();
            try {
                while (!$body->eof()) {
                    $chunk = $body->read(self::CHUNK);
                    if ($chunk === '') {
                        break;
                    }
                    if (fwrite($out, $chunk) === false) {
                        throw new BinaryDownloadException('Failed writing download to disk');
                    }
                }
            } finally {
                fclose($out);
            }
        } catch (\Throwable $e) {
            @unlink($tmp);
            throw $e;
        }

        return $tmp;
    }

    /**
     * Verifies the downloaded archive against its sha512 subresource-integrity digest.
     *
     * @param string $file
     * @param string $integrity
     *
     * @throws BinaryDownloadException
     *
     * @return void
     */
    private function verifyIntegrity(string $file, string $integrity): void
    {
        if (!str_starts_with($integrity, 'sha512-')) {
            throw new BinaryDownloadException(sprintf('Unsupported integrity format: %s', $integrity));
        }

        $raw = hash_file('sha512', $file, true);
        if ($raw === false) {
            throw new BinaryDownloadException('Unable to hash the downloaded archive');
        }

        $expected = substr($integrity, strlen('sha512-'));
        if (!hash_equals($expected, base64_encode($raw))) {
            throw new BinaryDownloadException('Integrity check failed: sha512 mismatch');
        }
    }

    /**
     * Stream the single $internalPath entry out of a gzipped tar archive into $destination.
     *
     * @param string $tgz
     * @param string $internalPath
     * @param string $destination
     *
     * @throws BinaryDownloadException
     *
     * @return void
     */
    private function extractBinary(string $tgz, string $internalPath, string $destination): void
    {
        $gz = gzopen($tgz, 'rb');
        if ($gz === false) {
            throw new BinaryDownloadException('Unable to open the downloaded archive');
        }

        try {
            while (true) {
                $header = $this->readExact($gz, 512);
                if ($header === null || trim($header, "\0") === '') {
                    break;
                }

                $name = rtrim(substr($header, 0, 100), "\0");
                $prefix = rtrim(substr($header, 345, 155), "\0");
                $fullName = $prefix !== '' ? $prefix . '/' . $name : $name;

                $sizeField = trim(substr($header, 124, 12));
                $size = $sizeField === '' ? 0 : (int) octdec($sizeField);

                if ($fullName === $internalPath) {
                    $this->streamEntry($gz, $size, $destination);

                    return;
                }

                $this->skip($gz, (int) (ceil($size / 512) * 512));
            }
        } finally {
            gzclose($gz);
        }

        throw new BinaryDownloadException(sprintf('Binary %s not found in archive', $internalPath));
    }

    /**
     * Write $size bytes from the archive stream to $destination, then consume tar block padding.
     *
     * @param resource $gz
     * @param int $size
     * @param string $destination
     *
     * @throws BinaryDownloadException
     *
     * @return void
     */
    private function streamEntry($gz, int $size, string $destination): void
    {
        $dir = dirname($destination);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new BinaryDownloadException(sprintf('Unable to create directory: %s', $dir));
        }

        $out = fopen($destination, 'wb');
        if ($out === false) {
            throw new BinaryDownloadException(sprintf('Unable to write binary: %s', $destination));
        }

        $remaining = $size;
        try {
            while ($remaining > 0) {
                $chunk = gzread($gz, min($remaining, self::CHUNK));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                if (fwrite($out, $chunk) === false) {
                    throw new BinaryDownloadException('Failed writing binary to disk');
                }
                $remaining -= strlen($chunk);
            }
        } finally {
            fclose($out);
        }

        if ($remaining > 0) {
            throw new BinaryDownloadException('Archive ended before the binary was fully read');
        }

        $this->skip($gz, (int) (ceil($size / 512) * 512) - $size);
    }

    /**
     * Discards the given number of bytes from the gzip stream (tar block padding or an unwanted entry).
     *
     * @param resource $gz
     * @param int $length
     *
     * @return void
     */
    private function skip($gz, int $length): void
    {
        $remaining = $length;
        while ($remaining > 0) {
            $chunk = gzread($gz, min($remaining, self::CHUNK));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $remaining -= strlen($chunk);
        }
    }

    /**
     * Read exactly $length bytes, or null at clean end-of-archive.
     *
     * @param resource $gz
     * @param int $length
     *
     * @return ?string
     */
    private function readExact($gz, int $length): ?string
    {
        $buffer = '';
        while (strlen($buffer) < $length) {
            $chunk = gzread($gz, $length - strlen($buffer));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buffer .= $chunk;
        }

        return $buffer === '' ? null : $buffer;
    }
}
