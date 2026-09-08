<?php

declare(strict_types=1);

/**
 * The wire shapes this installation can build drivers for.
 *
 * Ships with the two that matter; a host with a vendor neither reaches constructs
 * its own aggregate — shipped factories plus its own — and binds it over the
 * default, which is the estate's host-override-the-package pattern and the whole
 * reason hundreds of providers never touch this package's code: they are blocks
 * over the shipped shapes, and a genuinely new wire is the host's factory.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Registry;

use PHPdot\Ai\Bridge\Anthropic\Factory as AnthropicFactory;
use PHPdot\Ai\Bridge\OpenAi\Factory as OpenAiFactory;
use PHPdot\Ai\Contract\DriverFactoryInterface;
use PHPdot\Ai\Exception\LlmError;
use PHPdot\Container\Attribute\Singleton;

#[Singleton]
final class DriverFactories
{
    /** @var array<string, DriverFactoryInterface> By shape name. */
    private array $byShape = [];

    /**
     * @param list<DriverFactoryInterface> $factories The shapes to serve; defaults to the two shipped
     */
    public function __construct(array $factories = [])
    {
        foreach ($factories === [] ? self::defaults() : $factories as $factory) {
            $this->byShape[$factory->shape()->value] = $factory;
        }
    }

    /**
     * The factory for one shape.
     *
     * @param LlmShape $shape The wire shape
     *
     * @throws LlmError If no factory serves the shape
     *
     * @return DriverFactoryInterface
     */
    public function for(LlmShape $shape): DriverFactoryInterface
    {
        return $this->byShape[$shape->value]
            ?? throw new LlmError('No driver factory is registered for the [' . $shape->value . '] wire shape.');
    }

    /**
     * @return list<DriverFactoryInterface>
     */
    private static function defaults(): array
    {
        return [new AnthropicFactory(), new OpenAiFactory()];
    }
}
