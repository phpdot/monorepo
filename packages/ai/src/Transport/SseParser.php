<?php

declare(strict_types=1);

/**
 * Reassembles SSE frames from arbitrary chunk boundaries.
 *
 * curl hands over whatever arrived, which splits mid-line and mid-frame as a
 * matter of course. Both wire shapes stream SSE, so both drivers share this
 * rather than each carrying its own off-by-one.
 *
 * It reports COMPLETE frames only. A trailing partial line is held until the
 * chunk that finishes it, which is exactly the case a naive explode("\n")
 * gets wrong on the first slow network.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Transport;

final class SseParser
{
    private string $buffer = '';

    /**
     * Feed a chunk, take back every frame it completed.
     *
     * @param string $chunk Whatever curl just handed over
     *
     * @return list<array{event: ?string, data: string}> The frames now complete
     */
    public function push(string $chunk): array
    {
        $this->buffer .= $chunk;
        $frames = [];

        while (($cut = $this->frameEnd()) !== null) {
            [$raw, $length] = $cut;
            $this->buffer = substr($this->buffer, $length);

            $frame = $this->parse($raw);

            if ($frame !== null) {
                $frames[] = $frame;
            }
        }

        return $frames;
    }

    /**
     * Where the next complete frame ends, if one is complete.
     *
     * The EARLIEST end wins, across both terminator spellings. Searching one
     * spelling before the other would let a stream that mixes them swallow a
     * boundary — a later `\r\n\r\n` found first leaves an earlier `\n\n`
     * inside the frame, and two frames arrive merged into one corrupt blob.
     *
     * @return ?array{0: string, 1: int} The frame's raw text and how much to drop
     */
    private function frameEnd(): null|array
    {
        $best = null;

        foreach (["\r\n\r\n", "\n\n"] as $terminator) {
            $at = strpos($this->buffer, $terminator);

            if ($at !== false && ($best === null || $at + strlen($terminator) < $best[1])) {
                $best = [substr($this->buffer, 0, $at), $at + strlen($terminator)];
            }
        }

        return $best;
    }

    /**
     * One raw frame's event name and joined data.
     *
     * @param string $raw The frame, terminator already removed
     *
     * @return ?array{event: ?string, data: string} Null when the frame carried no data
     */
    private function parse(string $raw): null|array
    {
        $event = null;
        $data = [];

        $lines = preg_split('/\r\n|\r|\n/', $raw);

        foreach ($lines === false ? [$raw] : $lines as $line) {
            if ($line === '' || str_starts_with($line, ':')) {
                continue;
            }

            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));

                continue;
            }

            if (str_starts_with($line, 'data:')) {
                $data[] = ltrim(substr($line, 5), ' ');
            }
        }

        return $data === [] ? null : ['event' => $event, 'data' => implode("\n", $data)];
    }
}
