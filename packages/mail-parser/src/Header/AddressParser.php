<?php

declare(strict_types=1);

/**
 * Tokenizer-based parser for RFC 5322 email address headers supporting mailbox, group,
 * quoted strings, comments, and internationalized addresses.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Header;

use PHPdot\Mail\Parser\Result\Address;
use PHPdot\Mail\Parser\Result\AddressGroup;

final class AddressParser
{
    private const MAX_DEPTH = 50;

    private string $input;
    private int $pos;
    private int $length;

    /**
     * Parse an address-list header value (From, To, Cc, Bcc, Reply-To).
     *
     * @return list<Address|AddressGroup>
     */
    public function parse(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $this->input = $value;
        $this->pos = 0;
        $this->length = strlen($value);

        return $this->parseAddressList();
    }

    /**
     * @return list<Address|AddressGroup>
     */
    private function parseAddressList(int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $addresses = [];

        while ($this->pos < $this->length) {
            $this->skipWhitespace();

            if ($this->pos >= $this->length) {
                break;
            }

            $address = $this->parseAddressOrGroup($depth);
            if ($address !== null) {
                $addresses[] = $address;
            }

            $this->skipWhitespace();

            if ($this->pos < $this->length && $this->input[$this->pos] === ',') {
                $this->pos++;
            }
        }

        return $addresses;
    }

    private function parseAddressOrGroup(int $depth): Address|AddressGroup|null
    {
        $this->skipWhitespace();
        $savedPos = $this->pos;

        $nameOrAddr = $this->readDisplayNameOrAddr();

        $this->skipWhitespace();

        if ($this->pos < $this->length) {
            $char = $this->input[$this->pos];

            if ($char === ':') {
                $this->pos++;
                $groupAddresses = $this->parseGroupMailboxList($depth + 1);
                $this->skipWhitespace();
                if ($this->pos < $this->length && $this->input[$this->pos] === ';') {
                    $this->pos++;
                }
                return new AddressGroup($nameOrAddr, $groupAddresses);
            }

            if ($char === '<') {
                $addrSpec = $this->parseAngleAddr();
                if ($addrSpec !== null) {
                    return new Address($addrSpec, $nameOrAddr);
                }
            }
        }

        $this->pos = $savedPos;
        $addrSpec = $this->readAddrSpec();

        if ($addrSpec !== null && str_contains($addrSpec, '@')) {

            $this->skipWhitespace();
            $comment = $this->readComment();
            $name = $comment !== null ? $comment : '';
            return new Address($addrSpec, $name);
        }

        if ($addrSpec !== null && $addrSpec !== '') {

            $this->skipWhitespace();
            if ($this->pos < $this->length && $this->input[$this->pos] === '<') {
                $addr = $this->parseAngleAddr();
                if ($addr !== null) {
                    return new Address($addr, $addrSpec);
                }
            }
        }

        $this->skipToNextAddress();
        return null;
    }

    /**
     * @return list<Address>
     */
    private function parseGroupMailboxList(int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $addresses = [];

        while ($this->pos < $this->length) {
            $this->skipWhitespace();

            if ($this->pos >= $this->length) {
                break;
            }

            $char = $this->input[$this->pos];

            if ($char === ';') {
                break;
            }

            if ($char === ',') {
                $this->pos++;
                continue;
            }

            $address = $this->parseSingleMailbox();
            if ($address !== null) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }

    private function parseSingleMailbox(): Address|null
    {
        $this->skipWhitespace();
        $savedPos = $this->pos;

        $nameOrAddr = $this->readDisplayNameOrAddr();
        $this->skipWhitespace();

        if ($this->pos < $this->length && $this->input[$this->pos] === '<') {
            $addr = $this->parseAngleAddr();
            if ($addr !== null) {
                return new Address($addr, $nameOrAddr);
            }
        }

        $this->pos = $savedPos;
        $addr = $this->readAddrSpec();

        if ($addr !== null && str_contains($addr, '@')) {
            $this->skipWhitespace();
            $comment = $this->readComment();
            return new Address($addr, $comment ?? '');
        }

        return null;
    }

    private function parseAngleAddr(): string|null
    {
        if ($this->pos >= $this->length || $this->input[$this->pos] !== '<') {
            return null;
        }

        $this->pos++;

        $this->skipSourceRoute();

        $addr = $this->readAddrSpecInAngle();

        $this->skipWhitespace();
        if ($this->pos < $this->length && $this->input[$this->pos] === '>') {
            $this->pos++;
        }

        return $addr;
    }

    private function skipSourceRoute(): void
    {

        if ($this->pos >= $this->length || $this->input[$this->pos] !== '@') {
            return;
        }

        $closePos = strpos($this->input, '>', $this->pos);
        $windowEnd = $closePos === false ? $this->length : $closePos;
        $window = substr($this->input, $this->pos, $windowEnd - $this->pos);

        $colonPos = strpos($window, ':');
        if ($colonPos !== false && !str_contains(substr($window, 0, $colonPos), '<')) {
            $this->pos += $colonPos + 1;
        }
    }

    private function readAddrSpecInAngle(): string|null
    {
        $addr = '';

        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];

            if ($char === '>') {
                break;
            }

            if ($char === '"') {
                $addr .= $this->readQuotedString();
                continue;
            }

            $addr .= $char;
            $this->pos++;
        }

        $addr = trim($addr);
        return $addr !== '' ? $addr : null;
    }

    private function readAddrSpec(): string|null
    {
        $start = $this->pos;
        $addr = '';
        $inQuotes = false;

        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];

            if ($char === '"') {
                $addr .= $this->readQuotedString();
                continue;
            }

            if ($char === ',' || $char === ';' || $char === '<' || $char === '>') {
                break;
            }

            if ($char === ' ' || $char === "\t") {

                $lookAhead = $this->pos + 1;
                while ($lookAhead < $this->length && ($this->input[$lookAhead] === ' ' || $this->input[$lookAhead] === "\t")) {
                    $lookAhead++;
                }
                if ($lookAhead >= $this->length || in_array($this->input[$lookAhead], [',', ';', '<', '>', '('], true)) {
                    break;
                }

                break;
            }

            if ($char === '(') {
                break;
            }

            $addr .= $char;
            $this->pos++;
        }

        $addr = trim($addr);
        return $addr !== '' ? $addr : null;
    }

    private function readDisplayNameOrAddr(): string
    {
        $parts = [];

        while ($this->pos < $this->length) {
            $this->skipWhitespace();

            if ($this->pos >= $this->length) {
                break;
            }

            $char = $this->input[$this->pos];

            if ($char === '<' || $char === ',' || $char === ';' || $char === ':') {
                break;
            }

            if ($char === '"') {
                $parts[] = $this->readQuotedStringContent();
                continue;
            }

            if ($char === '(') {
                $comment = $this->readComment();
                if ($comment !== null) {
                    $parts[] = $comment;
                }
                continue;
            }

            $atom = $this->readAtom();
            if ($atom !== '') {
                $parts[] = $atom;
            } else {
                break;
            }
        }

        return trim(implode(' ', $parts));
    }

    private function readAtom(): string
    {
        $atom = '';
        $specials = ['(', ')', '<', '>', '@', ',', ';', ':', '\\', '"', '.', '[', ']', ' ', "\t", "\r", "\n"];

        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];

            if (in_array($char, $specials, true) && $char !== '.' && $char !== '@') {
                break;
            }

            $atom .= $char;
            $this->pos++;
        }

        return $atom;
    }

    private function readQuotedString(): string
    {
        if ($this->pos >= $this->length || $this->input[$this->pos] !== '"') {
            return '';
        }

        $result = '"';
        $this->pos++;
        $escaped = false;

        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];
            $this->pos++;

            if ($escaped) {
                $result .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                $result .= $char;
                continue;
            }

            if ($char === '"') {
                $result .= '"';
                return $result;
            }

            $result .= $char;
        }

        $result .= '"';
        return $result;
    }

    private function readQuotedStringContent(): string
    {
        if ($this->pos >= $this->length || $this->input[$this->pos] !== '"') {
            return '';
        }

        $result = '';
        $this->pos++;
        $escaped = false;

        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];
            $this->pos++;

            if ($escaped) {
                $result .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                return $result;
            }

            $result .= $char;
        }

        return $result;
    }

    private function readComment(): string|null
    {
        if ($this->pos >= $this->length || $this->input[$this->pos] !== '(') {
            return null;
        }

        $this->pos++;
        $content = '';
        $depth = 1;
        $escaped = false;

        while ($this->pos < $this->length && $depth > 0) {
            $char = $this->input[$this->pos];
            $this->pos++;

            if ($escaped) {
                $content .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $content .= $char;
                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth > 0) {
                    $content .= $char;
                }
                continue;
            }

            $content .= $char;
        }

        return trim($content);
    }

    private function skipWhitespace(): void
    {
        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];
            if ($char !== ' ' && $char !== "\t" && $char !== "\r" && $char !== "\n") {
                break;
            }
            $this->pos++;
        }
    }

    private function skipToNextAddress(): void
    {
        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];
            if ($char === ',' || $char === ';') {
                $this->pos++;
                return;
            }
            $this->pos++;
        }
    }
}
