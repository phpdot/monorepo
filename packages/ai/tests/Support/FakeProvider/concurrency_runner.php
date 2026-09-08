<?php

declare(strict_types=1);

/**
 * The Swoole concurrency proof, in one fresh process: with the production hook
 * flags set explicitly, four simultaneous streaming turns against the fake
 * provider's `concurrent` scenario — which holds every connection for 600ms —
 * must take ~600ms of wall time. Four blocking curls would serialize to
 * ~2400ms. Prints {"elapsedMs":N} on stdout.
 *
 * Runs in its own process because hook flags are process-global and must never
 * leak into the phpunit process. The server side runs as a plain child process
 * on purpose: Swoole hooks client-side stream operations, not server-side
 * accept loops, and the measurement must not depend on unhooked code.
 */

$autoload = __DIR__;
while (!is_file($autoload . '/vendor/autoload.php') && dirname($autoload) !== $autoload) {
    $autoload = dirname($autoload);
}
require $autoload . '/vendor/autoload.php';

use PHPdot\Ai\Transport\StreamingHttp;

$port = (int) ($argv[1] ?? 0);

if ($port <= 0) {
    fwrite(STDERR, "usage: concurrency_runner.php <port>\n");
    exit(1);
}

$logFile = sys_get_temp_dir() . '/phpdot_ai_concurrency_' . $port . '.server.log';
$requestsPath = sys_get_temp_dir() . '/phpdot_ai_concurrency_' . $port . '.jsonl';
@unlink($requestsPath);

$server = proc_open(
    [PHP_BINARY, __DIR__ . '/provider_runner.php', (string) $port, 'concurrent', $requestsPath],
    [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', $logFile, 'w'],
        2 => ['file', $logFile, 'w'],
    ],
    $pipes,
);

$deadline = microtime(true) + 5.0;

while (microtime(true) < $deadline) {
    $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);

    if (is_resource($probe)) {
        fclose($probe);

        break;
    }

    usleep(50_000);
}

\Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL | SWOOLE_HOOK_NATIVE_CURL);

$started = microtime(true);

\Swoole\Coroutine\run(static function () use ($port): void {
    for ($i = 0; $i < 4; $i++) {
        go(static function () use ($port): void {
            (new StreamingHttp(10))->post(
                'http://127.0.0.1:' . $port . '/chat/completions',
                ['model' => 'test'],
                [],
                static fn(): bool => true,
            );
        });
    }
});

$elapsedMs = (int) round((microtime(true) - $started) * 1000);

proc_terminate($server, SIGTERM);
usleep(100_000);
@unlink($logFile);
@unlink($requestsPath);

echo json_encode(['elapsedMs' => $elapsedMs]), "\n";
