<?php

declare(strict_types=1);

/**
 * Processes RFC 3676 format=flowed text by unwrapping soft line breaks, handling quote depth,
 * and respecting signature separators.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Content;

final class FlowedTextFormatter
{
    public function decode(string $text, bool $delSp = false): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $lines = explode("\n", $text);

        $result = [];
        $currentParagraph = '';
        $currentQuoteDepth = 0;
        $inParagraph = false;

        foreach ($lines as $line) {
            $quoteDepth = strspn($line, '>');
            $offset = $quoteDepth;
            $offset += ($line[$offset] ?? '') === ' ' ? 1 : 0;
            $content = substr($line, $offset);

            if ($content === '-- ') {
                if ($inParagraph) {
                    $result[] = $this->formatLine($currentParagraph, $currentQuoteDepth);
                    $inParagraph = false;
                    $currentParagraph = '';
                }
                $result[] = $this->formatLine('-- ', $quoteDepth);
                continue;
            }

            if ($inParagraph && $quoteDepth !== $currentQuoteDepth) {
                $result[] = $this->formatLine($currentParagraph, $currentQuoteDepth);
                $inParagraph = false;
                $currentParagraph = '';
            }

            $currentQuoteDepth = $quoteDepth;

            $isFlowed = $content !== '' && $content[strlen($content) - 1] === ' ';

            if ($isFlowed) {
                if ($delSp) {
                    $content = substr($content, 0, -1);
                }

                $currentParagraph .= $content;
                $inParagraph = true;
            } else {
                if ($inParagraph) {
                    $currentParagraph .= $content;
                    $result[] = $this->formatLine($currentParagraph, $currentQuoteDepth);
                    $inParagraph = false;
                    $currentParagraph = '';
                } else {
                    $result[] = $this->formatLine($content, $quoteDepth);
                }
            }
        }

        if ($inParagraph) {
            $result[] = $this->formatLine($currentParagraph, $currentQuoteDepth);
        }

        return implode("\r\n", $result);
    }

    private function formatLine(string $content, int $quoteDepth): string
    {
        if ($quoteDepth === 0) {
            return $content;
        }

        $prefix = str_repeat('>', $quoteDepth) . ' ';

        return $prefix . $content;
    }
}
