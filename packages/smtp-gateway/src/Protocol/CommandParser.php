<?php

declare(strict_types=1);

/**
 * Parses raw SMTP command lines into ParsedCommand DTOs.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Protocol;

use PHPdot\Mail\Gateway\DataType\DTO\ParsedCommand;
use PHPdot\Mail\Gateway\DataType\Enum\SmtpCommand;
use PHPdot\Mail\Gateway\Exception\ProtocolException;

/**
 * Parses SMTP command lines per RFC 5321 Section 4.1.
 */
final class CommandParser
{
    /**
     * Parse a raw line into a ParsedCommand.
     *
     * @throws ProtocolException If the command verb is unrecognized.
     */
    public function parse(string $line): ParsedCommand
    {
        $parts = explode(' ', $line, 2);
        $verb = strtoupper(trim($parts[0]));
        $argument = isset($parts[1]) ? trim($parts[1]) : '';

        $command = SmtpCommand::tryFrom($verb);
        if ($command === null) {
            throw new ProtocolException('Unrecognized command: ' . $verb);
        }

        return new ParsedCommand($command, $argument, $line);
    }

    /**
     * Extract email address from MAIL FROM:<addr> or RCPT TO:<addr>.
     *
     * RFC 5321 Section 4.1.1.2/4.1.1.3: path = "<" [ A-d-l ":" ] Mailbox ">"
     */
    public static function extractAddress(string $argument): string
    {

        if (preg_match('/<([^>]*)>/', $argument, $matches) === 1) {
            return trim($matches[1]);
        }

        $parts = explode(' ', $argument, 2);
        $addr = $parts[0];

        if (preg_match('/^(?:FROM|TO):\s*(.+)$/i', $addr, $m) === 1) {
            return trim($m[1]);
        }

        return trim($addr);
    }

    /**
     * Split "FROM:<path> params" into the address and its parameters, or null when the path breaks RFC 5321 4.1.2.
     * The address may hold no control character, space or bracket; a source route is dropped, `<>` is the empty path.
     *
     * @return array{string, string}|null
     */
    public static function parsePath(string $argument, string $keyword): array|null
    {
        $pattern = '/^' . preg_quote($keyword, '/') . ':[ ]?<(?:@[^:<>]*:)?([^<>]*)>(?:[ ]+(.*))?$/i';

        if (preg_match($pattern, $argument, $matches) !== 1) {
            return null;
        }

        $address = $matches[1];

        if (preg_match('/[\x00-\x20\x7F-\xFF]/', $address) === 1) {
            return null;
        }

        if ($address !== '' && strcasecmp($address, 'postmaster') !== 0 && preg_match('/^[^@]+@[^@]+$/', $address) !== 1) {
            return null;
        }

        return [$address, $matches[2] ?? ''];
    }

    /**
     * Extract SIZE parameter from MAIL FROM argument.
     *
     * RFC 1870: MAIL FROM:<addr> SIZE=123
     */
    public static function extractSize(string $argument): int|null
    {
        if (preg_match('/\bSIZE=(\d+)\b/i', $argument, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Extract BODY parameter from MAIL FROM argument.
     *
     * RFC 6152: MAIL FROM:<addr> BODY=8BITMIME
     */
    public static function extractBodyType(string $argument): string|null
    {
        if (preg_match('/\bBODY=(\S+)\b/i', $argument, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return null;
    }
}
