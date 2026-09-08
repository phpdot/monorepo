<?php

declare(strict_types=1);

/**
 * Builds Anthropic-shape drivers — `/messages`, `x-api-key`, top-level system.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Bridge\Anthropic;

use PHPdot\Ai\Bridge\Anthropic\Driver as AnthropicDriver;
use PHPdot\Ai\Contract\DriverFactoryInterface;
use PHPdot\Ai\Registry\LlmShape;
use PHPdot\Ai\Registry\ProviderBlock;
use PHPdot\Ai\Transport\StreamingHttp;

final class Factory implements DriverFactoryInterface
{
    public function shape(): LlmShape
    {
        return LlmShape::Anthropic;
    }

    /**
     * @param ProviderBlock $block The provider as configured
     * @param int $timeout Seconds a whole turn may take
     * @param int $maxTokens Ceiling on one answer
     *
     * @return AnthropicDriver
     */
    public function build(ProviderBlock $block, int $timeout, int $maxTokens): AnthropicDriver
    {
        return new AnthropicDriver(
            new StreamingHttp($timeout),
            $block->baseUrl,
            $block->apiKey,
            $block->version,
            $maxTokens,
        );
    }
}
