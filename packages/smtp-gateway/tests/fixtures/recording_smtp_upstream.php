<?php

/**
 * Child-process fixture: an upstream SMTP server that records every line it receives, for the relay transport tests.
 *
 * Usage: php recording_smtp_upstream.php <port> <log-file> <offer-starttls 0|1> <rcpt-reply-code>
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

$rawArgv = $_SERVER['argv'] ?? [];
$argv = [];
if (is_array($rawArgv)) {
    foreach ($rawArgv as $arg) {
        if (is_string($arg)) {
            $argv[] = $arg;
        }
    }
}

$port = (int) ($argv[1] ?? 0);
$log = $argv[2] ?? '';
$offerStartTls = ($argv[3] ?? '0') === '1';
$rcptCode = $argv[4] ?? '250';

$server = stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
if ($server === false || $log === '') {
    fwrite(STDERR, "cannot listen: {$errstr}\n");
    exit(1);
}

fwrite(STDOUT, "ready\n");

$client = stream_socket_accept($server, 10);
if ($client === false) {
    exit(1);
}

$send = static function (string $reply) use ($client): void {
    fwrite($client, $reply);
};
$record = static function (string $line) use ($log): void {
    file_put_contents($log, json_encode($line) . "\n", FILE_APPEND);
};

$send("220 upstream ESMTP\r\n");
$inData = false;

while (($line = fgets($client)) !== false) {
    $record($line);

    if ($inData) {
        if ($line === ".\r\n") {
            $inData = false;
            $send("250 ok id=upstream-1\r\n");
        }
        continue;
    }

    $verb = strtoupper(substr($line, 0, 4));

    if ($verb === 'EHLO') {
        $send("250-upstream\r\n" . ($offerStartTls ? "250-STARTTLS\r\n" : '') . "250 AUTH PLAIN\r\n");
    } elseif ($verb === 'STAR') {
        $send("220 go ahead\r\n");
        break;
    } elseif ($verb === 'AUTH') {
        $send("235 ok\r\n");
    } elseif ($verb === 'MAIL') {
        $send("250 ok\r\n");
    } elseif ($verb === 'RCPT') {
        $send($rcptCode . " recipient\r\n");
    } elseif ($verb === 'DATA') {
        $inData = true;
        $send("354 go\r\n");
    } elseif ($verb === 'QUIT') {
        $send("221 bye\r\n");
        break;
    } else {
        $send("500 what\r\n");
    }
}

fclose($client);
