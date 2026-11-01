<?php

declare(strict_types=1);

/**
 * A message copied from any stream into php://temp — memory to 2 MiB, disk past it — with CRLF line endings, then
 * read by offset through one buffered window.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Source;

use PHPdot\Mail\Parser\Contract\SourceInterface;
use PHPdot\Mail\Parser\Exception\SourceException;

final class StreamSource implements SourceInterface
{
    private const int CHUNK = 65_536;

    private const string SPOOL = 'php://temp/maxmemory:2097152';

    private int $windowStart = 0;

    private string $window = '';

    /**
     * @param resource $spool
     */
    private function __construct(
        private readonly mixed $spool,
        private readonly int $length,
        public readonly bool $truncated,
    ) {}

    public function __destruct()
    {
        fclose($this->spool);
    }

    /**
     * Copies `$stream` from where it stands, keeping at most `$limit` of its bytes (0 keeps all); `truncated` says
     * whether any were left behind.
     *
     * @param resource $stream
     *
     * @throws SourceException When the spool cannot be opened or written.
     */
    public static function spool($stream, int $limit): self
    {
        $spool = fopen(self::SPOOL, 'w+b');

        if ($spool === false) {
            throw SourceException::unwritable();
        }

        $kept = 0;
        $carry = '';
        $written = 0;

        while (!feof($stream) && ($limit === 0 || $kept < $limit)) {
            $chunk = fread($stream, $limit === 0 ? self::CHUNK : max(1, min(self::CHUNK, $limit - $kept)));

            if ($chunk === false || $chunk === '') {
                break;
            }

            $kept += strlen($chunk);
            $bytes = $carry . $chunk;
            $carry = str_ends_with($bytes, "\r") ? "\r" : '';
            $written += self::write($spool, LineEndings::canonical($carry === '' ? $bytes : substr($bytes, 0, -1)));
        }

        $written += self::write($spool, LineEndings::canonical($carry));
        $truncated = $limit > 0 && $kept >= $limit && fread($stream, 1) !== '';

        return new self($spool, $written, $truncated);
    }

    public function length(): int
    {
        return $this->length;
    }

    public function read(int $offset, int $length): string
    {
        $length = min($length, $this->length - $offset);

        if ($length <= 0 || $offset < 0) {
            return '';
        }

        if (!$this->holds($offset, $length)) {
            if ($length > self::CHUNK) {
                return $this->readAt($offset, $length);
            }

            $this->load($offset, self::CHUNK);
        }

        return substr($this->window, $offset - $this->windowStart, $length);
    }

    /**
     * Searches the window already read before reading more, each new window overlapping the last by the needle's
     * length, so a search costs the distance it covers whatever calls it.
     */
    public function find(string $needle, int $from, int $to): int|null
    {
        $width = strlen($needle);
        $to = min($to, $this->length);
        $at = $from;

        while ($width > 0 && $at + $width <= $to) {
            if (!$this->holds($at, $width)) {
                $this->load($at, max(self::CHUNK, 2 * $width));
            }

            $found = strpos($this->window, $needle, $at - $this->windowStart);

            if ($found !== false) {
                return $this->windowStart + $found + $width <= $to ? $this->windowStart + $found : null;
            }

            $windowEnd = $this->windowStart + strlen($this->window);

            if ($windowEnd >= $to) {
                return null;
            }

            $at = max($at + 1, $windowEnd - $width + 1);
        }

        return null;
    }

    private function holds(int $offset, int $length): bool
    {
        return $offset >= $this->windowStart && $offset + $length <= $this->windowStart + strlen($this->window);
    }

    private function load(int $offset, int $size): void
    {
        $this->windowStart = $offset;
        $this->window = $this->readAt($offset, min($size, $this->length - $offset));
    }

    private function readAt(int $offset, int $length): string
    {
        fseek($this->spool, $offset);
        $bytes = fread($this->spool, max(1, $length));

        return $bytes === false ? '' : $bytes;
    }

    /**
     * @param resource $spool
     *
     * @throws SourceException When the spool takes fewer bytes than it was given.
     */
    private static function write($spool, string $bytes): int
    {
        if ($bytes !== '' && fwrite($spool, $bytes) !== strlen($bytes)) {
            throw SourceException::unwritable();
        }

        return strlen($bytes);
    }
}
