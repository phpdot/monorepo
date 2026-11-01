# PHPdot Mail Parser

Zero-dependency, RFC-compliant EML/email parser for PHP 8.2+.

Parses email messages into clean, structured DTOs with full MIME support, streaming for large files, and built-in security limits.

## Requirements

| Requirement | Constraint |
|---|---|
| PHP | `>=8.5` |
| `ext-iconv` | `*` |
| `ext-mbstring` | `*` |

Development only:  `friendsofphp/php-cs-fixer` `^3.94`,  `phpstan/phpstan` `^2.0`,  `phpstan/phpstan-strict-rules` `^2.0`,  `phpunit/phpunit` `^13.0`.

## Installation

```bash
composer require phpdot/mail-parser
```

Requires PHP 8.2+ with `mbstring` and `iconv` extensions (both ship with PHP by default).

## Quick Start

```php
use PHPdot\Mail\Parser\MailParser;

$email = MailParser::parse(file_get_contents('message.eml'));

$email->subject;          // "Hello World"
$email->from->name;       // "John Doe"
$email->from->address;    // "john@example.com"
$email->text;             // plain text body
$email->html;             // HTML body
```

## Parsing

### From string

```php
$email = MailParser::parse($rawEmlString);
```

### From file

```php
$email = MailParser::parse(file_get_contents('/path/to/message.eml'));
```

### From stream (large emails)

```php
$parser = new MailParser();
$email = $parser->parseStream(fopen('huge-email.eml', 'r'));
```

The streaming parser reads line-by-line without loading the entire email into memory. Attachments are buffered via `php://temp` which auto-spills to disk after 2MB.

### From S3

```php
$s3Result = $s3Client->getObject(['Bucket' => 'my-bucket', 'Key' => 'email.eml']);
$stream = $s3Result['Body']->detach();

$parser = new MailParser();
$email = $parser->parseStream($stream);
fclose($stream);
```

## Email Properties

### Addresses

```php
$email->from;                    // ?Address — primary sender
$email->from->name;              // "John Doe"
$email->from->address;           // "john@example.com"

$email->sender;                  // ?Address — explicit Sender header

$email->to;                      // AddressList
$email->cc;                      // AddressList
$email->bcc;                     // AddressList
$email->replyTo;                 // AddressList

// AddressList is iterable, countable, and supports array access
$email->to[0]->address;         // "jane@example.com"
$email->to->first();            // ?Address
$email->to->isEmpty();          // bool
count($email->to);              // int
foreach ($email->to as $addr) { ... }
```

### Identity & Threading

```php
$email->subject;                 // ?string
$email->messageId;               // ?string — Message-ID without angle brackets
$email->date;                    // ?DateTimeImmutable

$email->inReplyTo;               // ?string — parent Message-ID
$email->references;              // list<string> — full thread chain
```

### Bodies

```php
$email->text;                    // ?string — plain text body
$email->html;                    // ?string — HTML body
$email->calendar;                // ?string — iCalendar body
```

### Attachments

```php
$email->attachments;             // AttachmentList

foreach ($email->attachments as $att) {
    $att->filename;              // "report.pdf"
    $att->mimeType;              // "application/pdf"
    $att->disposition;           // "attachment" or "inline"
    $att->size;                  // int (bytes)
    $att->contentId;             // for inline images (cid:)
    $att->content;               // decoded binary string
    $att->nestedEmail;           // ?Email — for message/rfc822 attachments
}

// Filtering
$email->attachments->inline();   // Attachment[] — inline only
$email->attachments->regular();  // Attachment[] — attachment only
$email->attachments->isEmpty();  // bool
$email->attachments->first();   // ?Attachment
```

### Attachment Streaming

For large attachments, use `stream()` to avoid holding content in memory twice:

```php
// Pipe directly to S3
$s3Client->putObject([
    'Bucket' => 'attachments',
    'Key' => $att->filename,
    'Body' => $att->stream(),
]);

// Pipe to file
$source = $att->stream();
$dest = fopen('/path/to/file', 'w');
stream_copy_to_stream($source, $dest);
fclose($dest);

// getContent() works for stream-created attachments too
$content = $att->getContent();
```

### Headers

```php
// Simple access — no chains, returns null if missing
$email->header('X-Mailer');              // ?string
$email->header('DKIM-Signature');        // ?string
$email->hasHeader('X-Custom');           // bool
$email->headerAll('Received');           // string[]
```

### Received Headers (Structured)

```php
$email->received;                        // list<ReceivedHop>

$hop = $email->received[0];
$hop->from;                              // "mail.example.com"
$hop->fromIp;                            // "209.85.220.41"
$hop->by;                                // "mx.example.com"
$hop->with;                              // "ESMTPS"
$hop->for;                               // "user@example.com"
$hop->date;                              // ?DateTimeImmutable
$hop->raw;                               // original header string
```

### DKIM Signatures (Structured)

```php
$email->dkim;                            // list<DkimSignature>

$sig = $email->dkim[0];
$sig->domain;                            // "example.com"
$sig->selector;                          // "s20210112"
$sig->algorithm;                         // "rsa-sha256"
$sig->headers;                           // "from:to:subject:date"
$sig->signature;                         // base64 signature string
$sig->bodyHash;                          // base64 body hash
$sig->canonicalization;                  // "relaxed/relaxed"
$sig->raw;                               // original header string
```

### SPF Result (Structured)

```php
$email->spf;                             // ?SpfResult

$email->spf->result;                     // "pass", "fail", "softfail", etc.
$email->spf->domain;                     // "example.com"
$email->spf->ip;                         // "209.85.220.41"
$email->spf->raw;                        // original header string
```

### Authentication Results (Structured)

```php
$email->authResults;                     // list<AuthResult>

$auth = $email->authResults[0];
$auth->server;                           // "mx.google.com"
$auth->dkim;                             // "pass"
$auth->dkimDomain;                       // "example.com"
$auth->spf;                              // "pass"
$auth->spfDomain;                        // "sender@example.com"
$auth->dmarc;                            // "pass"
$auth->dmarcDomain;                      // "example.com"
$auth->raw;                              // original header string
```

## Configuration

```php
use PHPdot\Mail\Parser\{MailParser, ParserConfig};

$config = new ParserConfig(
    strict: true,                    // throw on RFC violations (default: false)
    maxNestingDepth: 50,             // MIME nesting limit (default: 50)
    maxHeaderSize: 2_097_152,        // header block size limit in bytes (default: 2 MiB)
    maxAttachmentSize: 52_428_800,   // per-attachment limit in bytes (default: 50 MiB)
    maxMessageSize: 104_857_600,     // total message limit in bytes (default: 100 MiB)
    attachmentEncoding: 'string',    // 'string', 'base64', or 'none'
    decodeFlowed: true,              // process format=flowed text (default: true)
);

$email = MailParser::parse($raw, $config);
```

### Strict vs Lenient Mode

**Lenient mode** (default): best-effort parsing. Malformed headers are skipped, missing boundaries are detected heuristically, unknown charsets fall back to Windows-1252. Never crashes on real-world email.

**Strict mode**: throws specific exceptions on RFC violations:
- `InvalidHeaderException` — malformed headers
- `InvalidMimeStructureException` — missing boundaries, unknown encodings
- `CharsetConversionException` — unknown character sets
- `SecurityLimitException` — nesting depth, size limits exceeded

## RFC Compliance

| RFC | Coverage |
|-----|----------|
| RFC 5322 | Internet Message Format — headers, folding, addresses, dates |
| RFC 2045 | MIME Part 1 — Content-Type, Content-Transfer-Encoding |
| RFC 2046 | MIME Part 2 — multipart/mixed, alternative, related, digest |
| RFC 2047 | Encoded words in headers (`=?charset?B/Q?text?=`) |
| RFC 2049 | MIME conformance criteria |
| RFC 2183 | Content-Disposition (inline, attachment) |
| RFC 2231 | Parameter continuations, charset encoding |
| RFC 2392 | Content-ID / Message-ID URIs |
| RFC 3676 | format=flowed text/plain |
| RFC 5321 | Received header parsing |
| RFC 6376 | DKIM-Signature parsing |
| RFC 6531 | Internationalized email addresses |
| RFC 6532 | Internationalized email headers (UTF-8) |
| RFC 7208 | SPF (Received-SPF) parsing |
| RFC 8601 | Authentication-Results parsing |

## Testing

```bash
composer install
vendor/bin/phpunit
```

295 tests, 929 assertions covering unit tests, integration tests, security limits, malformed email resilience, and streaming parity.

## License

MIT License. See [LICENSE](LICENSE) for details.
