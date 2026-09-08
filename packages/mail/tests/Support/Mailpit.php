<?php

declare(strict_types=1);

/**
 * The test-side client for Mailpit's REST API. The delivery suite sends real
 * SMTP into Mailpit and then asserts on what actually arrived — the JSON the
 * API returns is the wire truth, not a re-reading of the composed Message.
 * Deliberately dependency-free: PHP's HTTP stream wrapper and ext-json only,
 * so the standalone rehearsal resolves nothing extra.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Tests\Support;

use RuntimeException;

final class Mailpit
{
    private readonly string $base;

    /**
     * Points the client at a Mailpit HTTP endpoint, e.g. http://127.0.0.1:8025.
     *
     * @param string $base
     */
    public function __construct(string $base)
    {
        $this->base = rtrim($base, '/');
    }

    /**
     * Builds the client from the environment the compose stack publishes:
     * MAILPIT_HOST and MAILPIT_HTTP_PORT, defaulting to Mailpit's own defaults.
     *
     * @return self
     */
    public static function fromEnv(): self
    {
        $host = getenv('MAILPIT_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('MAILPIT_HTTP_PORT') ?: 8025);

        return new self("http://{$host}:{$port}");
    }

    /**
     * Whether Mailpit's readiness endpoint answers. The connection warning is
     * suppressed on purpose: an absent server is an expected outcome here,
     * reported as false rather than raised.
     *
     * @return bool
     */
    public function isUp(): bool
    {
        return $this->fetch('GET', '/readyz', 2) !== false;
    }

    /**
     * Deletes every stored message so each test reads only its own mail.
     *
     * @return void
     */
    public function purge(): void
    {
        $this->request('DELETE', '/api/v1/messages');
    }

    /**
     * The stored messages, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function messages(): array
    {
        $decoded = self::decode($this->request('GET', '/api/v1/messages'));
        $messages = $decoded['messages'] ?? null;
        if (!is_array($messages)) {
            throw new RuntimeException('Mailpit returned no messages array.');
        }

        return array_values($messages);
    }

    /**
     * One stored message in full: envelope, bodies, attachments, metadata.
     *
     * @param string $id
     *
     * @return array<string, mixed>
     */
    public function message(string $id): array
    {
        return self::decode($this->request('GET', "/api/v1/message/{$id}"));
    }

    /**
     * The message's headers as Mailpit parsed them off the wire, name => values.
     *
     * @param string $id
     *
     * @return array<string, list<string>>
     */
    public function headers(string $id): array
    {
        return self::decode($this->request('GET', "/api/v1/message/{$id}/headers"));
    }

    /**
     * The decoded content of one MIME part, addressed by its part id.
     *
     * @param string $id
     * @param string $part
     *
     * @return string
     */
    public function part(string $id, string $part): string
    {
        return $this->request('GET', "/api/v1/message/{$id}/part/{$part}");
    }

    /**
     * Performs one HTTP call and returns the raw body, failing loudly.
     *
     * @param string $method
     * @param string $path
     *
     * @return string
     */
    private function request(string $method, string $path): string
    {
        $body = $this->fetch($method, $path, 5);
        if ($body === false) {
            throw new RuntimeException("Mailpit request failed: {$method} {$path}");
        }

        return $body;
    }

    /**
     * Performs one HTTP call, returning false when the endpoint is unreachable.
     *
     * @param string $method
     * @param string $path
     * @param int $timeoutSeconds
     *
     * @return string|false
     */
    private function fetch(string $method, string $path, int $timeoutSeconds): string|false
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
        ]);

        return @file_get_contents($this->base . $path, false, $context);
    }

    /**
     * Decodes an object-shaped JSON body, rejecting anything else.
     *
     * @param string $raw
     *
     * @return array<string, mixed>
     */
    private static function decode(string $raw): array
    {
        $decoded = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Mailpit replied with a non-object JSON body.');
        }

        return $decoded;
    }
}
