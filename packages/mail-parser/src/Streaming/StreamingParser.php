<?php

declare(strict_types=1);

/**
 * Line-by-line streaming email parser using a state machine for memory-efficient parsing
 * of large messages.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Streaming;

use PHPdot\Mail\Parser\Exception\SecurityLimitException;
use PHPdot\Mail\Parser\Header\HeaderCollection;
use PHPdot\Mail\Parser\Header\HeaderParser;
use PHPdot\Mail\Parser\Mime\MimeNode;
use PHPdot\Mail\Parser\ParserConfig;
use PHPdot\Mail\Parser\Result\MimeHeader;

final class StreamingParser
{
    private StreamState $state;
    private string $headerBuffer = '';
    private int $headerSize = 0;
    private int $totalBytesRead = 0;
    private PartContext|null $currentPart = null;

    /** @var \SplStack<BoundaryFrame> */
    private \SplStack $boundaryStack;

    private HeaderCollection|null $rootHeaders = null;
    private MimeNode|null $rootNode = null;
    private bool $isRootHeaders = true;

    public function __construct(
        private readonly ParserConfig $config,
        private readonly HeaderParser $headerParser,
    ) {
        $this->boundaryStack = new \SplStack();
        $this->state = StreamState::Header;
    }

    /**
     * Parse a stream line-by-line using a state machine.
     *
     * Raw body content is NOT decoded here — it is stored as-is in MimeNode,
     * and the existing MimeNode::getDecodedBody() pipeline handles decoding.
     *
     * @param resource $stream
     *
     * @return array{HeaderCollection, MimeNode}
     */
    public function parse($stream): array
    {
        $this->state = StreamState::Header;
        $this->headerBuffer = '';
        $this->headerSize = 0;
        $this->totalBytesRead = 0;
        $this->currentPart = null;
        $this->rootHeaders = null;
        $this->rootNode = null;
        $this->isRootHeaders = true;
        $this->boundaryStack = new \SplStack();

        while ($this->state !== StreamState::Finished) {
            $line = fgets($stream, 8192);

            if ($line === false) {
                $this->finalize();
                break;
            }

            $this->totalBytesRead += strlen($line);

            if ($this->config->maxMessageSize > 0 && $this->totalBytesRead > $this->config->maxMessageSize) {
                if ($this->config->strict) {
                    throw new SecurityLimitException(
                        sprintf('Message size exceeds maximum of %d bytes', $this->config->maxMessageSize),
                    );
                }
                $this->finalize();
                break;
            }

            $this->processLine($line);
        }

        if ($this->rootHeaders === null) {
            $this->rootHeaders = new HeaderCollection();
        }

        if ($this->rootNode === null) {
            $this->rootNode = new MimeNode(
                $this->rootHeaders,
                new MimeHeader('text/plain', ['charset' => 'us-ascii']),
                '',
            );
        }

        return [$this->rootHeaders, $this->rootNode];
    }

    private function processLine(string $line): void
    {
        match ($this->state) {
            StreamState::Header => $this->handleHeader($line),
            StreamState::Preamble => $this->handlePreamble($line),
            StreamState::Body => $this->handleBody($line),
            StreamState::Epilogue => $this->handleEpilogue($line),
            StreamState::Finished => null,
        };
    }

    private function handleHeader(string $line): void
    {
        $trimmed = rtrim($line, "\r\n");

        if ($trimmed === '') {
            $this->finalizeHeaders();
            return;
        }

        $this->headerSize += strlen($line);
        if ($this->config->maxHeaderSize > 0 && $this->headerSize > $this->config->maxHeaderSize) {
            if ($this->config->strict) {
                throw new SecurityLimitException(
                    sprintf('Header block exceeds maximum size of %d bytes', $this->config->maxHeaderSize),
                );
            }
            $this->finalizeHeaders();
            return;
        }

        $this->headerBuffer .= $line;
    }

    private function finalizeHeaders(): void
    {
        $headers = $this->headerParser->parse($this->headerBuffer);
        $this->headerBuffer = '';
        $this->headerSize = 0;

        $ctHeader = $headers->get('Content-Type');
        $contentType = ($ctHeader !== null && $ctHeader->structured !== null)
            ? $ctHeader->structured
            : new MimeHeader('text/plain', ['charset' => 'us-ascii']);

        if ($this->isRootHeaders) {
            $this->rootHeaders = $headers;
            $this->isRootHeaders = false;
        }

        $primaryType = explode('/', $contentType->value)[0];

        if ($primaryType === 'multipart') {
            $boundary = $contentType->getParam('boundary');

            if ($boundary === null || $boundary === '') {
                $this->startBodyPart($headers, $contentType);
                return;
            }

            $depth = $this->boundaryStack->count();
            if ($depth >= $this->config->maxNestingDepth) {
                if ($this->config->strict) {
                    throw new SecurityLimitException(
                        sprintf('MIME nesting depth exceeds maximum of %d', $this->config->maxNestingDepth),
                    );
                }
                $this->startBodyPart($headers, $contentType);
                return;
            }

            $frame = new BoundaryFrame($boundary, $contentType, $headers);
            $this->boundaryStack->push($frame);
            $this->state = StreamState::Preamble;
        } else {
            $this->startBodyPart($headers, $contentType);
        }
    }

    private function startBodyPart(HeaderCollection $headers, MimeHeader $contentType): void
    {
        $this->currentPart = new PartContext($headers, $contentType);

        if (!$this->currentPart->isTextBody()) {
            $this->currentPart->initStream();
        }

        $this->state = StreamState::Body;
    }

    private function handlePreamble(string $line): void
    {
        if ($this->boundaryStack->isEmpty()) {
            $this->state = StreamState::Finished;
            return;
        }

        $boundary = $this->boundaryStack->top()->boundary;
        $match = $this->matchBoundary($line, $boundary);

        if ($match === 'open') {
            $this->state = StreamState::Header;
        } elseif ($match === 'close') {
            $this->popBoundaryFrame();
        }
    }

    private function handleBody(string $line): void
    {

        if (!$this->boundaryStack->isEmpty()) {
            $boundary = $this->boundaryStack->top()->boundary;
            $match = $this->matchBoundary($line, $boundary);

            if ($match === 'open') {
                $this->finalizeCurrentPart(boundaryTerminated: true);
                $this->state = StreamState::Header;
                return;
            }

            if ($match === 'close') {
                $this->finalizeCurrentPart(boundaryTerminated: true);
                $this->popBoundaryFrame();
                return;
            }
        }

        if ($this->currentPart !== null) {

            $this->currentPart->flushPendingCrlf();

            $content = rtrim($line, "\r\n");
            $lineEnding = substr($line, strlen($content));

            $this->currentPart->writeBody($content);
            $this->currentPart->pendingCrlf = $lineEnding;
        }
    }

    private function handleEpilogue(string $line): void
    {
        if ($this->boundaryStack->isEmpty()) {
            return;
        }

        $boundary = $this->boundaryStack->top()->boundary;
        $match = $this->matchBoundary($line, $boundary);

        if ($match === 'open') {
            $this->state = StreamState::Header;
        } elseif ($match === 'close') {
            $this->popBoundaryFrame();
        }
    }

    private function finalizeCurrentPart(bool $boundaryTerminated = false): void
    {
        if ($this->currentPart === null) {
            return;
        }

        $part = $this->currentPart;

        if (!$boundaryTerminated) {

            $part->flushPendingCrlf();
        }

        $rawBody = $part->getBody();

        $rawBody = str_replace("\r\n", "\n", $rawBody);
        $rawBody = str_replace("\r", "\n", $rawBody);
        $rawBody = str_replace("\n", "\r\n", $rawBody);

        if ($boundaryTerminated) {

            $rawBody = rtrim($rawBody, "\r\n");
        }

        $node = new MimeNode(
            $part->headers,
            $part->contentType,
            $rawBody,
        );

        $this->currentPart = null;

        if (!$this->boundaryStack->isEmpty()) {
            $this->boundaryStack->top()->children[] = $node;
        } else {
            $this->rootNode = $node;
        }
    }

    private function popBoundaryFrame(): void
    {
        if ($this->boundaryStack->isEmpty()) {
            $this->state = StreamState::Finished;
            return;
        }

        $frame = $this->boundaryStack->pop();

        $multipartNode = new MimeNode(
            $frame->headers,
            $frame->contentType,
            '',
            $frame->children,
        );

        if (!$this->boundaryStack->isEmpty()) {
            $this->boundaryStack->top()->children[] = $multipartNode;
            $this->state = StreamState::Epilogue;
        } else {
            $this->rootNode = $multipartNode;
            $this->state = StreamState::Epilogue;
        }
    }

    /**
     * @return 'open'|'close'|false
     */
    private function matchBoundary(string $line, string $boundary): string|false
    {
        $trimmed = rtrim($line, "\r\n \t");
        $delimiter = '--' . $boundary;

        if ($trimmed === $delimiter . '--') {
            return 'close';
        }

        if ($trimmed === $delimiter) {
            return 'open';
        }

        return false;
    }

    private function finalize(): void
    {
        if ($this->headerBuffer !== '') {
            $this->finalizeHeaders();
        }

        if ($this->currentPart !== null) {
            $this->finalizeCurrentPart(boundaryTerminated: false);
        }

        while (!$this->boundaryStack->isEmpty()) {
            $this->popBoundaryFrame();
        }

        $this->state = StreamState::Finished;
    }
}
