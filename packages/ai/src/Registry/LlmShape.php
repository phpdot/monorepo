<?php

declare(strict_types=1);

/**
 * The wire shapes this package speaks, which is not the same as the providers it
 * reaches.
 *
 * `OpenAi` is a PROTOCOL and not a company: OpenAI, Groq, DeepSeek, Together,
 * Fireworks, OpenRouter, Ollama and Gemini's compatibility endpoint all answer
 * the same `/chat/completions` shape, and each of them is a named provider block
 * in the host's configuration — a base URL and a key — over the one driver.
 * A shape is added only when a vendor cannot be reached by an existing one, and
 * that is a driver plus a factory, never a hundred enum cases.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Registry;

enum LlmShape: string
{
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';
}
