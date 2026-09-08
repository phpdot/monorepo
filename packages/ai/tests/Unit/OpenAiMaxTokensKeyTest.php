<?php

declare(strict_types=1);

namespace PHPdot\Ai\Tests\Unit;

use PHPdot\Ai\Bridge\OpenAi\Driver;
use PHPdot\Ai\Conversation\ConversationDTO;
use PHPdot\Ai\Conversation\MessageDTO;
use PHPdot\Ai\Conversation\MessageRole;
use PHPdot\Ai\Event\LlmEvent;
use PHPdot\Ai\Registry\LlmShape;
use PHPdot\Ai\Registry\ProviderBlock;
use PHPdot\Ai\Tests\Support\FakeProvider\ProviderHarness;
use PHPUnit\Framework\Attributes\Test;

/**
 * The ceiling parameter's wire name: the reasoning family rejects max_tokens
 * outright, so the driver names the parameter every current model accepts —
 * and a compatible vendor that never moved overrides it in its block.
 */
final class OpenAiMaxTokensKeyTest extends ProviderHarness
{
    #[Test]
    public function theCeilingRidesTheModernParameterName(): void
    {
        $this->serve('openai-text');

        $this->driver()->stream(
            new ConversationDTO([new MessageDTO(MessageRole::User, 'hi')]),
            'gpt-test',
            [],
            static fn(LlmEvent $event): bool => true,
        );

        [$request] = $this->requests();

        self::assertArrayHasKey('max_completion_tokens', $request['body']);
        self::assertSame(64, $request['body']['max_completion_tokens']);
        self::assertArrayNotHasKey('max_tokens', $request['body'], 'the name the reasoning family rejects must not ride the wire');
    }

    #[Test]
    public function aBlockMayOverrideTheNameForAVendorThatNeverMoved(): void
    {
        $this->serve('openai-text');

        $block = $this->block();
        $block = new ProviderBlock(
            name: $block->name,
            shape: $block->shape,
            label: $block->label,
            baseUrl: $this->baseUrl(),
            apiKey: $block->apiKey,
            version: $block->version,
            models: $block->models,
            maxTokensKey: 'max_tokens',
        );

        (new \PHPdot\Ai\Bridge\OpenAi\Factory())->build($block, 10, 64)->stream(
            new ConversationDTO([new MessageDTO(MessageRole::User, 'hi')]),
            'gpt-test',
            [],
            static fn(LlmEvent $event): bool => true,
        );

        [$request] = $this->requests();

        self::assertArrayHasKey('max_tokens', $request['body']);
        self::assertArrayNotHasKey('max_completion_tokens', $request['body']);
    }

    /**
     * @return Driver
     */
    private function driver(): Driver
    {
        return (new \PHPdot\Ai\Bridge\OpenAi\Factory())->build($this->block(), 10, 64);
    }

    /**
     * @return ProviderBlock
     */
    private function block(): ProviderBlock
    {
        return new ProviderBlock(
            name: 'openai',
            shape: LlmShape::OpenAi,
            label: 'OpenAI',
            baseUrl: $this->baseUrl(),
            apiKey: 'test-openai-key',
            version: '',
            models: ['gpt-test' => 'GPT Test'],
        );
    }

    /**
     * @return string
     */
    protected function scenario(): string
    {
        return 'openai-text';
    }
}
