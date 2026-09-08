<?php

declare(strict_types=1);

/**
 * Every configured LLM provider, reachable by name from one injectable accessor.
 *
 * Providers are CONFIGURATION, not code: a named block per provider — `openai`,
 * `anthropic`, `groq`, `deepseek`, `ollama`, as many as the host wants — each
 * declaring the wire `driver` it speaks. Two shapes cover the market, so the
 * blocks scale into the hundreds while the factories stay at two; a vendor
 * neither shape reaches is a host-written factory bound through
 * {@see DriverFactories}, not a change here.
 *
 * The same bargain the rest of the estate strikes with its client registries: a
 * service type-hints this and autowires, instead of each module building a
 * client of its own.
 *
 *     public function __construct(Drivers $drivers) {}
 *     $this->drivers->for('groq')->stream($conversation, $model, $onEvent);
 *
 * A provider with no key is NOT an error here. It is reported as unconfigured
 * so the picker can grey it out, because the operator who has not set a key yet
 * is better served by a disabled option than by an exception at the first turn.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Registry;

use PHPdot\Ai\Contract\LlmDriver;
use PHPdot\Ai\Exception\LlmError;
use PHPdot\Ai\Internal\Values;
use PHPdot\Config\Configuration;
use PHPdot\Container\Attribute\Singleton;

#[Singleton]
final readonly class Drivers
{
    public function __construct(
        private Configuration $config,
        private DriverFactories $factories,
    ) {}

    /**
     * Every configured provider name, in configuration order.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->blocks());
    }

    /**
     * The provider a new conversation starts on.
     *
     * Falls back to the first CONFIGURED provider when the configured default
     * has no key — a default nobody can use is worse than an opinion.
     *
     * @return string
     */
    public function default(): string
    {
        $named = Values::string($this->config->get('ai.default'));

        if ($named !== '' && $this->isConfigured($named)) {
            return $named;
        }

        foreach ($this->names() as $name) {
            if ($this->isConfigured($name)) {
                return $name;
            }
        }

        return $named !== '' ? $named : ($this->names()[0] ?? '');
    }

    /**
     * Does this provider have a key?
     *
     * @param string $name The provider
     *
     * @return bool
     */
    public function isConfigured(string $name): bool
    {
        return $this->block($name)->configured();
    }

    /**
     * The models this provider offers, as identifier => label.
     *
     * @param string $name The provider
     *
     * @return array<string, string>
     */
    public function models(string $name): array
    {
        return $this->block($name)->models;
    }

    /**
     * Every provider, with what the picker needs to render it.
     *
     * @return list<array{name: string, label: string, shape: string, configured: bool, models: array<string, string>}>
     */
    public function catalogue(): array
    {
        $catalogue = [];

        foreach ($this->blocks() as $name => $block) {
            $catalogue[] = [
                'name'       => $name,
                'label'      => $block->label,
                'shape'      => $block->shape->value,
                'configured' => $block->configured(),
                'models'     => $block->models,
            ];
        }

        return $catalogue;
    }

    /**
     * The driver for a named provider.
     *
     * @param string $name The provider, by its configuration key
     *
     * @throws LlmError If no block carries the name, its shape is unknown, or the key is absent
     *
     * @return LlmDriver
     */
    public function for(string $name): LlmDriver
    {
        $block = $this->block($name);

        if (!$block->configured()) {
            throw new LlmError('No API key is configured for ' . $name . '.');
        }

        return $this->factories
            ->for($block->shape)
            ->build(
                $block,
                Values::int($this->config->get('ai.timeout'), 300),
                Values::int($this->config->get('ai.maxTokens'), 8192),
            );
    }

    /**
     * Every provider block, by name.
     *
     * @return array<string, ProviderBlock>
     */
    private function blocks(): array
    {
        $blocks = [];

        foreach (Values::array($this->config->get('ai.providers')) as $name => $settings) {
            if (!is_string($name) || !is_array($settings)) {
                continue;
            }

            $blocks[$name] = $this->block($name, $settings);
        }

        return $blocks;
    }

    /**
     * One provider's block, read out of its configuration.
     *
     * @param string $name The provider
     * @param array<mixed>|null $settings The block's settings, when already read
     *
     * @throws LlmError If no block carries the name, or its declared shape is unknown
     *
     * @return ProviderBlock
     */
    private function block(string $name, null|array $settings = null): ProviderBlock
    {
        $settings ??= Values::array($this->config->get('ai.providers.' . $name));

        if ($settings === []) {
            throw new LlmError('No provider is configured under [' . $name . '].');
        }

        $declared = Values::string($settings['driver'] ?? '', $name);
        $shape = LlmShape::tryFrom($declared);

        if ($shape === null) {
            throw new LlmError(sprintf(
                'Provider [%s] declares the wire shape [%s], which no registered factory serves.',
                $name,
                $declared,
            ));
        }

        /** @var array<string, string> $models */
        $models = Values::array($settings['models'] ?? []);

        return new ProviderBlock(
            name: $name,
            shape: $shape,
            label: Values::string($settings['label'] ?? '', $name),
            baseUrl: Values::string(
                $settings['baseUrl'] ?? '',
                $shape === LlmShape::Anthropic ? 'https://api.anthropic.com/v1' : 'https://api.openai.com/v1',
            ),
            apiKey: Values::string($settings['apiKey'] ?? ''),
            version: Values::string($settings['version'] ?? '', '2023-06-01'),
            models: $models,
            maxTokensKey: Values::string($settings['maxTokensKey'] ?? '', 'max_completion_tokens'),
        );
    }
}
