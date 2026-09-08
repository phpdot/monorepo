<?php

declare(strict_types=1);

/**
 * One named provider as the host configured it — the unit the registry is made of.
 *
 * A block is a configuration key with a wire shape: `groq` over the OpenAI shape,
 * `anthropic` over its own, `ollama` local over OpenAI again. The number of blocks
 * is the number of providers, and it grows by configuration alone; the number of
 * shapes grows only when a vendor invents a genuinely different wire.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Registry;

final readonly class ProviderBlock
{
    /**
     * @param string $name The block's key, as the host and the picker know it
     * @param LlmShape $shape The wire shape its driver speaks
     * @param string $label What the picker shows
     * @param string $baseUrl The API root, no trailing slash
     * @param string $apiKey The key; empty means unconfigured
     * @param string $version The anthropic-version header, where the shape wants one
     * @param array<string, string> $models Model identifier => label
     * @param string $maxTokensKey The OpenAI shape's ceiling parameter name — `max_completion_tokens`
     *                             for OpenAI itself, `max_tokens` for a compatible vendor that never moved
     */
    public function __construct(
        public string $name,
        public LlmShape $shape,
        public string $label,
        public string $baseUrl,
        public string $apiKey,
        public string $version,
        public array $models,
        public string $maxTokensKey = 'max_completion_tokens',
    ) {}

    /**
     * Whether a key was set for this provider.
     *
     * @return bool
     */
    public function configured(): bool
    {
        return $this->apiKey !== '';
    }
}
