<?php

declare(strict_types=1);

/**
 * Builds SMTP reply lines per RFC 5321.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Protocol;

use PHPdot\Mail\Gateway\DataType\Enum\ReplyCode;

/**
 * Builds SMTP reply strings.
 *
 * RFC 5321 Section 4.2: Reply format is "code SP text CRLF" for single-line,
 * or "code-text CRLF ... code SP text CRLF" for multi-line.
 */
final class ReplyBuilder
{
    private string $hostname;

    public function __construct(string $hostname = 'localhost')
    {
        $this->hostname = $hostname;
    }

    /**
     * Single-line reply: "code SP text\r\n"
     */
    public function reply(ReplyCode $code, string $text): string
    {
        return $code->value . ' ' . self::clean($text) . "\r\n";
    }

    /**
     * Multi-line reply: intermediate lines use "code-text\r\n", last uses "code SP text\r\n".
     *
     * @param list<string> $lines
     */
    public function multiLine(ReplyCode $code, array $lines): string
    {
        if ($lines === []) {
            return $this->reply($code, '');
        }

        $result = '';
        $last = count($lines) - 1;

        foreach ($lines as $i => $line) {
            if ($i === $last) {
                $result .= $code->value . ' ' . self::clean($line) . "\r\n";
            } else {
                $result .= $code->value . '-' . self::clean($line) . "\r\n";
            }
        }

        return $result;
    }

    /**
     * Reply text with every control character made a space, so no client or host text can end the reply line.
     */
    private static function clean(string $text): string
    {
        return preg_replace('/[\x00-\x1F\x7F]/', ' ', $text) ?? '';
    }

    /**
     * 220 greeting sent when client connects.
     */
    public function greeting(string $text = ''): string
    {
        $msg = $this->hostname . ' ESMTP ' . ($text !== '' ? $text : 'phpdot/smtp-gateway ready');
        return $this->reply(ReplyCode::ServiceReady, $msg);
    }

    /**
     * 250 EHLO response with capability list.
     *
     * @param list<string> $extensions
     */
    public function ehlo(array $extensions = []): string
    {
        $lines = [$this->hostname . ' Hello'];
        foreach ($extensions as $ext) {
            $lines[] = $ext;
        }

        return $this->multiLine(ReplyCode::Ok, $lines);
    }

    /**
     * 250 OK response.
     */
    public function ok(string $text = 'OK'): string
    {
        return $this->reply(ReplyCode::Ok, $text);
    }

    /**
     * 250 OK with queued message ID.
     */
    public function queued(string $messageId): string
    {
        return $this->reply(ReplyCode::Ok, 'OK queued as ' . $messageId);
    }

    /**
     * 220 Ready to start TLS.
     */
    public function readyForTls(): string
    {
        return $this->reply(ReplyCode::ServiceReady, 'Ready to start TLS');
    }

    /**
     * 221 Bye.
     */
    public function bye(string $text = ''): string
    {
        $msg = $this->hostname . ' ' . ($text !== '' ? $text : 'Service closing transmission channel');
        return $this->reply(ReplyCode::ServiceClosing, $msg);
    }

    /**
     * 235 Authentication successful.
     */
    public function authSuccess(): string
    {
        return $this->reply(ReplyCode::AuthSuccess, 'Authentication successful');
    }

    /**
     * 334 AUTH continuation challenge.
     */
    public function authChallenge(string $challenge = ''): string
    {
        return $this->reply(ReplyCode::AuthContinue, $challenge);
    }

    /**
     * 354 Start mail input.
     */
    public function startData(): string
    {
        return $this->reply(ReplyCode::StartMailInput, 'End data with <CR><LF>.<CR><LF>');
    }

    /**
     * 421 Service not available — disconnect.
     */
    public function serviceUnavailable(string $reason = ''): string
    {
        $msg = $this->hostname . ' ' . ($reason !== '' ? $reason : 'Service not available, closing');
        return $this->reply(ReplyCode::ServiceNotAvailable, $msg);
    }

    /**
     * 451 Local error.
     */
    public function localError(string $text = 'Requested action aborted: local error'): string
    {
        return $this->reply(ReplyCode::LocalError, $text);
    }

    /**
     * 500 Syntax error.
     */
    public function syntaxError(string $text = 'Syntax error, command unrecognized'): string
    {
        return $this->reply(ReplyCode::SyntaxError, $text);
    }

    /**
     * 501 Syntax error in parameters.
     */
    public function paramError(string $text = 'Syntax error in parameters or arguments'): string
    {
        return $this->reply(ReplyCode::SyntaxParamError, $text);
    }

    /**
     * 502 Command not implemented.
     */
    public function notImplemented(string $text = 'Command not implemented'): string
    {
        return $this->reply(ReplyCode::NotImplemented, $text);
    }

    /**
     * 503 Bad sequence of commands.
     */
    public function badSequence(string $text = 'Bad sequence of commands'): string
    {
        return $this->reply(ReplyCode::BadSequence, $text);
    }

    /**
     * 530 Authentication required.
     */
    public function authRequired(): string
    {
        return $this->reply(ReplyCode::AuthRequired, 'Authentication required');
    }

    /**
     * 535 Authentication failed.
     */
    public function authFailed(string $text = 'Authentication credentials invalid'): string
    {
        return $this->reply(ReplyCode::AuthFailed, $text);
    }

    /**
     * 552 Message too large.
     */
    public function messageTooLarge(int $maxSize): string
    {
        return $this->reply(ReplyCode::ExceededStorage, 'Message exceeds maximum size of ' . $maxSize . ' bytes');
    }

    /**
     * 554 Transaction failed.
     */
    public function transactionFailed(string $text = 'Transaction failed'): string
    {
        return $this->reply(ReplyCode::TransactionFailed, $text);
    }
}
