<?php

declare(strict_types=1);

/**
 * Builds OpenAI-shape drivers — `/chat/completions`, bearer, and the shape that
 * most of the market speaks by compatibility. One factory, hundreds of providers:
 * each is a block with its own base URL and key.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Bridge\OpenAi;

use PHPdot\Ai\Bridge\OpenAi\Driver as OpenAiDriver;
use PHPdot\Ai\Contract\DriverFactoryInterface;
use PHPdot\Ai\Registry\LlmShape;
use PHPdot\Ai\Registry\ProviderBlock;
use PHPdot\Ai\Transport\StreamingHttp;

final class Factory implements DriverFactoryInterface
{
    public function shape(): LlmShape
    {
        return LlmShape::OpenAi;
    }

    /**
     * @param ProviderBlock $block The provider as configured
     * @param int $timeout Seconds a whole turn may take
     * @param int $maxTokens Ceiling on one answer
     *
     * @return OpenAiDriver
     */
    public function build(ProviderBlock $block, int $timeout, int $maxTokens): OpenAiDriver
    {
        return new OpenAiDriver(
            new StreamingHttp($timeout),
            $block->baseUrl,
            $block->apiKey,
            $maxTokens,
            $block->maxTokensKey,
        );
    }
}
