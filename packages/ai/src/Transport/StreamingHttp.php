<?php

declare(strict_types=1);

/**
 * One streaming POST, shared by both wire shapes.
 *
 * The same curl the rest of the estate already uses, with CURLOPT_WRITEFUNCTION
 * instead of RETURNTRANSFER so frames are handled as they land. `hookFlags` is
 * SWOOLE_HOOK_ALL and SWOOLE_HOOK_NATIVE_CURL is on, so this yields the
 * coroutine rather than blocking the worker for the minutes a turn can take.
 *
 * The status is read in the HEADER callback and not after the fact, because by
 * then the body is spent. A non-2xx body is an error document rather than an
 * event stream, so it is BUFFERED and raised whole — running it through the SSE
 * parser would report "the provider said nothing", which is the least useful
 * true statement available.
 *
 * Nothing closes the handle: since PHP 8.0 it is an object freed with the last
 * reference, and curl_close is deprecated in 8.5.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Transport;

use Closure;
use PHPdot\Ai\Exception\LlmError;

final readonly class StreamingHttp
{
    /**
     * @param int $timeout Seconds a whole turn may take
     */
    public function __construct(private int $timeout) {}

    /**
     * POST a JSON body and hand back each SSE frame as it completes.
     *
     * @param string $url Where to post
     * @param array<string, mixed> $payload The request body
     * @param list<string> $headers Request headers, already formed
     * @param Closure(string, ?string): bool $onFrame Receives (data, event); false abandons the turn
     *
     * @throws LlmError If the provider did not answer, or refused
     *
     * @return bool True when the stream ran to its end; false when the consumer abandoned it
     */
    public function post(string $url, array $payload, array $headers, Closure $onFrame): bool
    {
        $parser = new SseParser();
        $status = 0;
        $errorBody = '';
        $abandoned = false;

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER     => [...$headers, 'Content-Type: application/json', 'Accept: text/event-stream'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => false,

            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$status): int {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $found) === 1) {
                    $status = (int) $found[1];
                }

                return strlen($line);
            },

            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (
                &$status,
                &$errorBody,
                &$abandoned,
                $parser,
                $onFrame,
            ): int {
                $length = strlen($chunk);

                if ($status < 200 || $status > 299) {
                    $errorBody .= $chunk;

                    return $length;
                }

                foreach ($parser->push($chunk) as $frame) {
                    if ($onFrame($frame['data'], $frame['event']) === false) {
                        $abandoned = true;

                        return 0;
                    }
                }

                return $length;
            },
        ]);

        curl_exec($curl);
        $failed = curl_errno($curl);
        unset($curl);

        /*
         * ABANDONMENT IS ANSWERED, not swallowed. The transport has nothing left
         * to say to a consumer who stopped reading, and the caller — the driver —
         * has a tail of assembled tool calls and a Done event that must not fire
         * for a turn nobody is listening to. Returning before any throw keeps
         * "the reader left" from ever being mistaken for "the provider failed".
         */
        if ($abandoned) {
            return false;
        }

        /*
         * TRANSPORT FAILURES ARE TESTED BEFORE the status range: a request that
         * never connected leaves the status at 0, and the range check would
         * report it as "refused (HTTP 0)" — naming a refusal that never
         * happened and hiding the curl error that says what did.
         */
        if ($failed === CURLE_OPERATION_TIMEDOUT) {
            throw new LlmError('The model took longer than ' . $this->timeout . ' seconds to answer.');
        }

        if ($failed !== 0) {
            throw new LlmError('The connection to the model failed: ' . curl_strerror($failed) . '.');
        }

        if ($status < 200 || $status > 299) {
            throw new LlmError($this->explain($status, $errorBody));
        }

        return true;
    }

    /**
     * A refusal, said in one line.
     *
     * Both shapes nest their reason under `error.message`; anything else is
     * reported by status alone rather than by echoing an unknown document at
     * the reader.
     *
     * @param int $status The HTTP status
     * @param string $body Whatever the provider sent instead of a stream
     *
     * @return string
     */
    private function explain(int $status, string $body): string
    {
        $reason = Json::get(Json::decode($body), ['error', 'message']);

        if (is_string($reason) && $reason !== '') {
            return $reason;
        }

        return match (true) {
            $status === 401 => 'The provider rejected the API key.',
            $status === 429 => 'The provider is rate limiting this key.',
            $status >= 500  => 'The provider is not answering.',
            default         => 'The provider refused the request (HTTP ' . $status . ').',
        };
    }
}
