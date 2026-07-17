<?php

declare(strict_types=1);

/**
 * Parser
 *
 * Orchestrates the Lexer and Resolver to parse .env file content into key-value pairs.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env\Parser;

use PHPdot\Env\Exception\EncodingException;
use PHPdot\Env\Exception\ParseException;

final class Parser
{
    /**
     * Create a parser from its lexer and resolver stages.
     *
     * @param Lexer $lexer The tokenizer.
     * @param Resolver $resolver The variable interpolation resolver.
     */
    public function __construct(
        private readonly Lexer $lexer,
        private readonly Resolver $resolver,
    ) {}

    /**
     * Create a new Parser instance with default dependencies.
     *
     * @return self A fully configured Parser.
     */
    public static function create(): self
    {
        return new self(new Lexer(), new Resolver());
    }

    /**
     * Parse .env content into key-value pairs.
     *
     * @param string $content Raw file content.
     * @param string $file File path for error messages.
     * @param array<string, string> $predefined Pre-existing values for interpolation.
     *
     * @throws EncodingException On BOM or non-UTF-8.
     * @throws ParseException On syntax or interpolation errors.
     *
     * @return array<string, string> Parsed key-value pairs, last value wins for duplicates.
     */
    public function parse(string $content, string $file = '', array $predefined = []): array
    {
        $entries = $this->lexer->tokenize($content, $file);
        $resolved = $this->resolver->resolve($entries, $predefined);

        $result = [];

        foreach ($resolved as $entry) {
            $result[$entry->key] = $entry->value;
        }

        return $result;
    }
}
