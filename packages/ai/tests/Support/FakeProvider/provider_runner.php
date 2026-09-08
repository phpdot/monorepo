<?php

declare(strict_types=1);

/**
 * The fake provider: a raw stream-socket HTTP server in plain core PHP, serving
 * recorded SSE fixtures (or raw error bodies) and logging every request it
 * receives as a JSON line — the executable wire spec the driver tests assert
 * against. Launched as a separate process by ProviderHarness; argv is port,
 * scenario, requestsJsonl.
 *
 * Plain stream sockets and not `php -S` on purpose: `php -S` answers one request
 * at a time, which would serialize even a correctly-yielding client, and it
 * takes byte-level pacing out of the tests' hands.
 *
 * Connections that arrive carrying nothing — readiness probes, which connect
 * and close — are dropped, not consumed as requests.
 */

$port = (int) ($argv[1] ?? 0);
$scenario = (string) ($argv[2] ?? '');
$requestsPath = (string) ($argv[3] ?? '');

if ($port <= 0 || $scenario === '' || $requestsPath === '') {
    fwrite(STDERR, "usage: provider_runner.php <port> <scenario> <requestsJsonl>\n");
    exit(1);
}

$server = stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $errstr);

if ($server === false) {
    fwrite(STDERR, "could not listen: {$errstr}\n");
    exit(1);
}

echo "READY\n";

/**
 * Read one HTTP request off a connection: headers, then Content-Length of body.
 * Answers null when the connection carried nothing at all.
 *
 * @param resource $connection
 *
 * @return array{method: string, path: string, headers: list<string>, body: string}|null
 */
function readRequest($connection): null|array
{
    $buffer = '';

    while (!str_contains($buffer, "\r\n\r\n")) {
        $chunk = fread($connection, 4096);

        if ($chunk === false || $chunk === '') {
            break;
        }

        $buffer .= $chunk;
    }

    if (trim($buffer) === '') {
        return null;
    }

    [$head, $body] = explode("\r\n\r\n", $buffer, 2) + ['', ''];
    $lines = explode("\r\n", (string) $head);
    $requestLine = array_shift($lines) ?? '';

    while (strlen((string) $body) < (int) (preg_match('/Content-Length: (\d+)/i', (string) $head, $found) ? $found[1] : 0)) {
        $chunk = fread($connection, 4096);

        if ($chunk === false || $chunk === '') {
            break;
        }

        $body .= $chunk;
    }

    [$method, $path] = explode(' ', $requestLine) + ['', ''];

    return ['method' => $method, 'path' => $path, 'headers' => $lines, 'body' => (string) $body];
}

/**
 * Accept connections until one arrives carrying a real request, and log it.
 *
 * @param resource $server
 *
 * @return resource The connection carrying the request
 */
function awaitRequest($server, string $requestsPath)
{
    $deadline = microtime(true) + 10.0;

    while (microtime(true) < $deadline) {
        $connection = @stream_socket_accept($server, 0.2);

        if ($connection === false) {
            continue;
        }

        $request = readRequest($connection);

        if ($request === null) {
            fclose($connection);

            continue;
        }

        file_put_contents(
            $requestsPath,
            json_encode([
                'method'  => $request['method'],
                'path'    => $request['path'],
                'headers' => $request['headers'],
                'body'    => json_decode($request['body'], true),
                'raw'     => $request['body'],
            ]) . "\n",
            FILE_APPEND,
        );

        return $connection;
    }

    exit(1);
}

/**
 * @param resource $connection
 */
function respond($connection, int $status, string $body, string $contentType): void
{
    fwrite($connection, "HTTP/1.1 {$status} X\r\nContent-Type: {$contentType}\r\nConnection: close\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
}

/**
 * @param resource $connection
 */
function respondStream($connection, string $fixture, bool $dribble): void
{
    fwrite($connection, "HTTP/1.1 200 X\r\nContent-Type: text/event-stream\r\nCache-Control: no-cache\r\n\r\n");

    $body = (string) file_get_contents(__DIR__ . '/fixtures/' . $fixture);

    if ($dribble) {
        foreach (str_split($body, 7) as $byte) {
            fwrite($connection, $byte);
            flush();
            usleep(1_000);
        }

        return;
    }

    foreach (explode("\n\n", rtrim($body, "\n")) as $frame) {
        fwrite($connection, $frame . "\n\n");
        flush();
        usleep(5_000);
    }
}

switch ($scenario) {
    case 'concurrent':
        $clients = [];
        $deadline = microtime(true) + 5.0;

        while (count($clients) < 4 && microtime(true) < $deadline) {
            $connection = @stream_socket_accept($server, 0.05);

            if ($connection === false) {
                continue;
            }

            $request = readRequest($connection);

            if ($request === null) {
                fclose($connection);

                continue;
            }

            $clients[] = [$connection, $request];
        }

        usleep(600_000);

        foreach ($clients as [$connection, $request]) {
            file_put_contents(
                $requestsPath,
                json_encode([
                    'method'  => $request['method'],
                    'path'    => $request['path'],
                    'headers' => $request['headers'],
                    'body'    => json_decode($request['body'], true),
                    'raw'     => $request['body'],
                ]) . "\n",
                FILE_APPEND,
            );
            respondStream($connection, 'openai-text.txt', dribble: false);
        }

        break;

    case 'hold':
        $connection = awaitRequest($server, $requestsPath);
        sleep(30);
        break;

    case 'slow':
        $connection = awaitRequest($server, $requestsPath);
        fwrite($connection, "HTTP/1.1 200 X\r\nContent-Type: text/event-stream\r\nCache-Control: no-cache\r\n\r\n");

        for ($i = 0; $i < 12; $i++) {
            fwrite($connection, 'data: {"i":' . $i . '}' . "\n\n");
            flush();
            usleep(150_000);
        }

        break;

    case 'http-401-reason':
        respond(awaitRequest($server, $requestsPath), 401, '{"error":{"message":"Incorrect API key provided."}}', 'application/json');
        break;

    case 'http-401-plain':
        respond(awaitRequest($server, $requestsPath), 401, 'nope', 'text/plain');
        break;

    case 'http-429':
        respond(awaitRequest($server, $requestsPath), 429, 'slow down', 'text/plain');
        break;

    case 'http-500':
        respond(awaitRequest($server, $requestsPath), 500, 'kaboom', 'text/plain');
        break;

    default:
        $fixtures = [
            'anthropic-text'      => 'anthropic-text.txt',
            'anthropic-tool-call' => 'anthropic-tool-call.txt',
            'anthropic-error'     => 'anthropic-error.txt',
            'openai-text'         => 'openai-text.txt',
            'openai-tool-call'    => 'openai-tool-call.txt',
            'openai-dribble'      => 'openai-text.txt',
        ];

        respondStream(
            awaitRequest($server, $requestsPath),
            $fixtures[$scenario] ?? 'openai-text.txt',
            dribble: $scenario === 'openai-dribble',
        );
        break;
}
