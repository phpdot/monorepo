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
 * Splits incoming byte stream into CRLF-terminated lines.
 *
 * SMTP is strictly line-oriented (RFC 5321 Section 2.3.8).
 * No literal handling needed — DATA mode is managed by the session.
 */
final class LineParser
{
    private string $buffer = '';

    /** Max line length per RFC 5321 Section 4.5.3.1.6: 512 including CRLF. */
    private int $maxLineLength;

    /** Max buffer size to prevent memory exhaustion (default 27MB — slightly over 25MB message limit). */
    private int $maxBufferSize;

    public function __construct(int $maxLineLength = 512, int $maxBufferSize = 28_311_552)
    {
        $this->maxLineLength = $maxLineLength;
        $this->maxBufferSize = $maxBufferSize;
    }

    /**
     * Feed raw bytes and return complete lines.
     *
     * @return list<string> Complete lines without CRLF terminator.
     */
    public function feed(string $data): array
    {
        $this->buffer .= $data;
        $lines = [];

        while ($this->buffer !== '') {
            $crlfPos = strpos($this->buffer, "\r\n");
            if ($crlfPos === false) {

                $lfPos = strpos($this->buffer, "\n");
                if ($lfPos !== false) {
                    $crlfPos = $lfPos;
                    $crlfLen = 1;
                } else {

                    if ($this->maxLineLength > 0 && strlen($this->buffer) > $this->maxLineLength) {

                        $line = substr($this->buffer, 0, $this->maxLineLength);
                        $this->buffer = '';
                        $lines[] = $line;
                    }
                    break;
                }
            } else {
                $crlfLen = 2;
            }

            $line = substr($this->buffer, 0, $crlfPos);
            $this->buffer = substr($this->buffer, $crlfPos + $crlfLen);

            if ($this->maxLineLength > 0 && strlen($line) > $this->maxLineLength) {
                $line = substr($line, 0, $this->maxLineLength);
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Feed raw bytes in DATA mode — lines can be up to 998 chars (RFC 5322 Section 2.1.1).
     *
     * @return list<string>
     */
    public function feedData(string $data): array
    {
        $this->buffer .= $data;

        if ($this->maxBufferSize > 0 && strlen($this->buffer) > $this->maxBufferSize) {
            $line = substr($this->buffer, 0, $this->maxBufferSize);
            $this->buffer = '';
            return [$line];
        }

        $lines = [];

        while ($this->buffer !== '') {
            $crlfPos = strpos($this->buffer, "\r\n");
            if ($crlfPos === false) {
                $lfPos = strpos($this->buffer, "\n");
                if ($lfPos !== false) {
                    $crlfPos = $lfPos;
                    $crlfLen = 1;
                } else {
                    break;
                }
            } else {
                $crlfLen = 2;
            }

            $line = substr($this->buffer, 0, $crlfPos);
            $this->buffer = substr($this->buffer, $crlfPos + $crlfLen);
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Returns any buffered but incomplete data.
     */
    public function pending(): string
    {
        return $this->buffer;
    }

    /**
     * Reset the parser state.
     */
    public function reset(): void
    {
        $this->buffer = '';
    }
}
