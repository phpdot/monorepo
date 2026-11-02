<?php

declare(strict_types=1);

/**
 * Splits incoming byte stream into complete SMTP lines.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Protocol;

/**
 * Splits incoming bytes into lines ended by CRLF alone, so a bare CR or LF never ends a line (RFC 5321 Section 2.3.8).
 * A line longer than its limit is dropped whole, up to the CRLF that ends it, and reported once.
 */
final class LineParser
{
    private string $buffer = '';

    private int $offset = 0;

    private bool $discarding = false;

    public function __construct(
        private readonly int $maxLineLength = 512,
        private readonly int $maxBufferSize = 28_311_552,
    ) {}

    /**
     * Add received bytes to the buffer, cutting what was already read once per call.
     */
    public function append(string $data): void
    {
        if ($this->offset > 0) {
            $this->buffer = substr($this->buffer, $this->offset);
            $this->offset = 0;
        }

        $this->buffer .= $data;

        if ($this->discarding) {
            $this->skipDiscarded();
        }
    }

    /**
     * The next complete line without its CRLF: null while none is buffered, false for one past $maxLength, dropped.
     */
    public function next(int $maxLength): string|false|null
    {
        if ($this->discarding) {
            return null;
        }

        $end = strpos($this->buffer, "\r\n", $this->offset);

        if ($end === false) {
            if (strlen($this->buffer) - $this->offset > $maxLength + 1) {
                $this->discarding = true;
                $this->skipDiscarded();
                return false;
            }

            return null;
        }

        $line = substr($this->buffer, $this->offset, $end - $this->offset);
        $this->offset = $end + 2;

        if (strlen($line) > $maxLength) {
            return false;
        }

        return $line;
    }

    /**
     * Feed raw bytes and return complete command lines; over-long lines are dropped.
     *
     * @return list<string>
     */
    public function feed(string $data): array
    {
        return $this->drain($data, $this->maxLineLength);
    }

    /**
     * Feed raw bytes in DATA mode, where a line may run to the buffer limit.
     *
     * @return list<string>
     */
    public function feedData(string $data): array
    {
        return $this->drain($data, $this->maxBufferSize);
    }

    /**
     * Returns any buffered but incomplete data.
     */
    public function pending(): string
    {
        return substr($this->buffer, $this->offset);
    }

    /**
     * Reset the parser state.
     */
    public function reset(): void
    {
        $this->buffer = '';
        $this->offset = 0;
        $this->discarding = false;
    }

    /**
     * @return list<string>
     */
    private function drain(string $data, int $maxLength): array
    {
        $this->append($data);
        $lines = [];

        while (($line = $this->next($maxLength)) !== null) {
            if ($line !== false) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function skipDiscarded(): void
    {
        $end = strpos($this->buffer, "\r\n", $this->offset);

        if ($end === false) {
            $keep = str_ends_with($this->buffer, "\r") ? "\r" : '';
            $this->buffer = $keep;
            $this->offset = 0;
            return;
        }

        $this->offset = $end + 2;
        $this->discarding = false;
    }
}
