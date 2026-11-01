<?php

declare(strict_types=1);

/**
 * Parses raw email header blocks into structured Header objects with RFC 5322 unfolding,
 * encoded word decoding, and specialized parsing for address, date, and MIME headers.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Header;

use PHPdot\Mail\Parser\Content\CharsetConverter;
use PHPdot\Mail\Parser\Exception\SecurityLimitException;
use PHPdot\Mail\Parser\ParserConfig;
use PHPdot\Mail\Parser\Result\Address;
use PHPdot\Mail\Parser\Result\AddressGroup;
use PHPdot\Mail\Parser\Rfc2231\ParameterDecoder;

final class HeaderParser
{
    private const ADDRESS_HEADERS = [
        'from', 'to', 'cc', 'bcc', 'reply-to', 'sender',
        'resent-from', 'resent-to', 'resent-cc', 'resent-bcc', 'resent-sender',
    ];

    private const STRUCTURED_HEADERS = [
        'content-type', 'content-disposition',
    ];

    private readonly EncodedWordDecoder $encodedWordDecoder;
    private readonly AddressParser $addressParser;
    private readonly DateParser $dateParser;
    private readonly StructuredHeaderParser $structuredHeaderParser;

    public function __construct(
        private readonly ParserConfig $config,
    ) {
        $charsetConverter = new CharsetConverter();
        $this->encodedWordDecoder = new EncodedWordDecoder($charsetConverter);
        $this->addressParser = new AddressParser();
        $this->dateParser = new DateParser();
        $this->structuredHeaderParser = new StructuredHeaderParser(
            new ParameterDecoder($charsetConverter),
        );
    }

    public function parse(string $headerBlock): HeaderCollection
    {

        if ($this->config->maxHeaderSize > 0 && strlen($headerBlock) > $this->config->maxHeaderSize) {
            if ($this->config->strict) {
                throw new SecurityLimitException(
                    sprintf('Header block exceeds maximum size of %d bytes', $this->config->maxHeaderSize),
                );
            }
            $headerBlock = substr($headerBlock, 0, $this->config->maxHeaderSize);
        }

        $headerBlock = $this->normalizeLineEndings($headerBlock);

        $rawHeaders = $this->unfold($headerBlock);
        $headers = [];

        foreach ($rawHeaders as [$name, $rawValue]) {
            $headers[] = $this->parseHeader($name, $rawValue);
        }

        return new HeaderCollection($headers);
    }

    public function getEncodedWordDecoder(): EncodedWordDecoder
    {
        return $this->encodedWordDecoder;
    }

    public function getStructuredHeaderParser(): StructuredHeaderParser
    {
        return $this->structuredHeaderParser;
    }

    public function getDateParser(): DateParser
    {
        return $this->dateParser;
    }

    private function normalizeLineEndings(string $text): string
    {

        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        return str_replace("\n", "\r\n", $text);
    }

    /**
     * Unfold header lines and return [name, rawValue] pairs.
     *
     * @return list<array{string, string}>
     */
    private function unfold(string $headerBlock): array
    {
        $lines = explode("\r\n", $headerBlock);
        $headers = [];
        $currentLine = null;

        foreach ($lines as $line) {
            if ($line === '') {

                if ($currentLine !== null) {
                    $headers[] = $this->splitHeaderLine($currentLine);
                }
                break;
            }

            if (($line[0] === ' ' || $line[0] === "\t") && $currentLine !== null) {

                $currentLine .= ' ' . ltrim($line);
                continue;
            }

            if ($currentLine !== null) {
                $headers[] = $this->splitHeaderLine($currentLine);
            }
            $currentLine = $line;
        }

        if ($currentLine !== null) {
            $headers[] = $this->splitHeaderLine($currentLine);
        }

        return array_values(array_filter($headers, fn($h) => $h !== null));
    }

    /**
     * @return array{string, string}|null
     */
    private function splitHeaderLine(string $line): array|null
    {
        $colonPos = strpos($line, ':');
        if ($colonPos === false) {
            return null;
        }

        $name = substr($line, 0, $colonPos);
        $value = ltrim(substr($line, $colonPos + 1));

        if (preg_match('/\s/', $name) === 1) {
            return null;
        }

        return [$name, $value];
    }

    /**
     * Each address's display name decoded, as it is shown; the addresses themselves exactly as written.
     *
     * @param list<Address|AddressGroup> $parsed
     *
     * @return list<Address|AddressGroup>
     */
    private function decodedNames(array $parsed): array
    {
        $named = fn(Address $one): Address => new Address($one->address, $this->encodedWordDecoder->decode($one->name));

        return array_map(fn(Address|AddressGroup $one): Address|AddressGroup => $one instanceof AddressGroup
            ? new AddressGroup($this->encodedWordDecoder->decode($one->name), array_map($named, $one->addresses))
            : $named($one), $parsed);
    }

    private function parseHeader(string $name, string $rawValue): Header
    {
        $lowerName = strtolower($name);

        $decodedValue = $this->encodedWordDecoder->decode($rawValue);

        $structured = null;
        $addresses = null;
        $dateTime = null;

        if (in_array($lowerName, self::STRUCTURED_HEADERS, true)) {
            $structured = $this->structuredHeaderParser->parse($rawValue);
        }

        if (in_array($lowerName, self::ADDRESS_HEADERS, true)) {
            $addresses = $this->decodedNames($this->addressParser->parse($rawValue));
        }

        if ($lowerName === 'date') {
            $dateTime = $this->dateParser->parse($decodedValue);
        }

        return new Header(
            name: $name,
            value: $decodedValue,
            rawValue: $rawValue,
            structured: $structured,
            addresses: $addresses,
            dateTime: $dateTime,
        );
    }
}
