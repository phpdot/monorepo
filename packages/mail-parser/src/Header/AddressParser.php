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

    /**
     * Printable ASCII, or letters, digits and marks after a letter or digit, never a blank-looking one such as U+3164.
     */
    private const VISIBLE = '/^(?:[\x20-\x7e]|(?![\x{115F}\x{1160}\x{3164}\x{FFA0}\x{2800}\x{180E}])[\p{L}\p{N}]\p{M}*(?<![\x{034F}\x{FE00}-\x{FE0F}\x{E0100}-\x{E01EF}]))*$/u';

    /**
     * An RFC 2047 encoded word, read as one token so the `:` or `,` some senders leave unencoded in it stays in the name.
     */
    private const ENCODED_WORD = '/\G=\?[^?\s]+\?[BbQq]\?[^?\s]*\?=/';

    /**
     * An addr-spec once comments and folding are gone (RFC 5322 §3.4.1, its obsolete dots, RFC 6532 UTF-8): a local
     * part of atoms, dots and quoted strings, one `@`, then a dotted domain or a domain literal.
     */
    private const ADDR_SPEC = '/^(?>[A-Za-z0-9!#$%&\'*+\/=?^_`{|}~\x80-\xff.-]|"(?>[^"\\\\\r\n]|\\\\.)*")+'
        . '@(?>(?>[A-Za-z0-9!#$%&\'*+\/=?^_`{|}~\x80-\xff.-])+|\[(?>[^\[\]\\\\\r\n]|\\\\.)*\])$/';

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

            $before = $this->pos;
            $address = $this->parseAddressOrGroup($depth);
            if ($address !== null) {
                $addresses[] = $address;
            }

            if ($this->pos === $before) {
                $this->skipToNextAddress();
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
                if ($addrSpec === false) {
                    $this->skipToNextAddress();

                    return null;
                }
                if ($addrSpec !== null) {
                    return new Address($addrSpec, $nameOrAddr);
                }
            }
        }

        $this->pos = $savedPos;
        $addrSpec = $this->readAddrSpec();

        if ($addrSpec !== null && str_contains($addrSpec, '@')) {
            if (!self::isAddrSpec($addrSpec)) {
                $this->skipToNextAddress();

                return null;
            }

            $this->skipWhitespace();

            return new Address($addrSpec, $this->readComment() ?? '');
        }

        if ($addrSpec !== null && $addrSpec !== '') {
            $this->skipWhitespace();
            if ($this->pos < $this->length && $this->input[$this->pos] === '<') {
                $addr = $this->parseAngleAddr();
                if (is_string($addr)) {
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

            $before = $this->pos;
            $address = $this->parseSingleMailbox();
            if ($address !== null) {
                $addresses[] = $address;
            }

            if ($this->pos === $before) {
                $this->skipToGroupItem();
            }
        }

        return $addresses;
    }

    private function parseSingleMailbox(): Address|null
    {
        $this->skipWhitespace();
        $savedPos = $this->pos;

        $nameOrAddr = $this->readDisplayNameOrAddr();
        $scanEnd = $this->pos;
        $this->skipWhitespace();

        if ($this->pos < $this->length && $this->input[$this->pos] === '<') {
            $addr = $this->parseAngleAddr();
            if ($addr === false) {
                $this->skipToNextAddress();

                return null;
            }
            if ($addr !== null) {
                return new Address($addr, $nameOrAddr);
            }
        }

        $this->pos = $savedPos;
        $addr = $this->readAddrSpec();

        if ($addr !== null && str_contains($addr, '@') && self::isAddrSpec($addr)) {
            $this->skipWhitespace();

            return new Address($addr, $this->readComment() ?? '');
        }

        $this->pos = max($this->pos, $scanEnd);

        return null;
    }

    /**
     * The address inside `<…>`: null when there is none here, false when the brackets hold anything but one addr-spec,
     * in which case the reading has moved past the closing bracket.
     */
    private function parseAngleAddr(): string|false|null
    {
        if ($this->pos >= $this->length || $this->input[$this->pos] !== '<') {
            return null;
        }

        $this->pos++;

        $this->skipSourceRoute();

        $addr = $this->readAddrSpecInAngle();

        if ($this->pos < $this->length && $this->input[$this->pos] === '>') {
            $this->pos++;
        }

        if ($addr === null) {
            return null;
        }

        return $addr !== false && self::isAddrSpec($addr) ? $addr : false;
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

    /**
     * Reads to the closing `>`, dropping comments and folding white space: false when a bracket, comma, semicolon or
     * colon shows the brackets do not hold a single address.
     */
    private function readAddrSpecInAngle(): string|false|null
    {
        $addr = '';
        $malformed = false;

        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];

            if ($char === '>') {
                break;
            }

            if ($char === '"') {
                $addr .= $this->readQuotedString();
                continue;
            }

            if ($char === '(') {
                $this->readComment();
                continue;
            }

            if (in_array($char, ['<', ',', ';', ':', '\\', ')'], true)) {
                $malformed = true;
            } elseif ($char !== ' ' && $char !== "\t" && $char !== "\r" && $char !== "\n") {
                $addr .= $char;
            }

            $this->pos++;
        }

        if ($malformed) {
            return false;
        }

        return $addr !== '' ? $addr : null;
    }

    /**
     * Whether a string is one address that can pass for no other: its non-ASCII characters letters, digits or marks
     * after one, none blank-looking, and its domain valid IDNA (UTS 46) when ext-intl is there to check it.
     */
    private static function isAddrSpec(string $addr): bool
    {
        if (preg_match(self::ADDR_SPEC, $addr) !== 1 || preg_match(self::VISIBLE, $addr) !== 1) {
            return false;
        }

        $domain = substr($addr, (int) strrpos($addr, '@') + 1);

        if (!function_exists('idn_to_ascii') || str_starts_with($domain, '[') || preg_match('/[\x80-\xff]/', $domain) !== 1) {
            return true;
        }

        $checks = IDNA_NONTRANSITIONAL_TO_ASCII | IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ | IDNA_USE_STD3_RULES;

        return idn_to_ascii($domain, $checks, INTL_IDNA_VARIANT_UTS46) !== false;
    }

    private function readAddrSpec(): string|null
    {
        $addr = '';

        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];

            if ($char === '"') {
                $addr .= $this->readQuotedString();
                continue;
            }

            if ($char === ',' || $char === ';' || $char === '<' || $char === '>') {
                break;
            }

            if ($char === ' ' || $char === "\t" || $char === '(') {
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

            if ($char === '=' && preg_match(self::ENCODED_WORD, $this->input, $word, 0, $this->pos) === 1) {
                $parts[] = $word[0];
                $this->pos += strlen($word[0]);
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
            if ($atom === '') {
                break;
            }

            $parts[] = $atom;

            if (str_contains($atom, '@') && !$this->angleFollows()) {
                break;
            }
        }

        return trim(implode(' ', $parts));
    }

    /**
     * Whether the next character past blanks opens an angle address, as after a name written as an address.
     */
    private function angleFollows(): bool
    {
        $at = $this->pos + strspn($this->input, " \t", $this->pos);

        return $at < $this->length && $this->input[$at] === '<';
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

    /**
     * Moves past what no mailbox could be read from, to the next comma or to the semicolon that closes the group.
     */
    private function skipToGroupItem(): void
    {
        if ($this->input[$this->pos] === '(') {
            $this->readComment();

            return;
        }

        $this->pos++;

        while ($this->pos < $this->length && $this->input[$this->pos] !== ',' && $this->input[$this->pos] !== ';') {
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
