<?php

declare(strict_types=1);

namespace PHPdot\Ai\Tests\Integration;

use PHPdot\Ai\Bridge\Anthropic\Driver as AnthropicDriver;
use PHPdot\Ai\Bridge\OpenAi\Driver as OpenAiDriver;
use PHPdot\Ai\Contract\ToolDefinition;
use PHPdot\Ai\Conversation\ConversationDTO;
use PHPdot\Ai\Conversation\MessageDTO;
use PHPdot\Ai\Conversation\MessageRole;
use PHPdot\Ai\Event\LlmEvent;
use PHPdot\Ai\Event\LlmEventType;
use PHPdot\Ai\Tests\Support\FakeProvider\ProviderHarness;
use PHPdot\Ai\Transport\StreamingHttp;
use PHPUnit\Framework\Attributes\Test;

/**
 * Both drivers end to end against the fake provider: the events they emit, the
 * shapes they SEND (asserted from the provider's request log — the executable
 * wire spec), and the two failure channels.
 */
final class DriverWireTest extends ProviderHarness
{
    #[Test]
    public function anthropicStreamsTextAndUsage(): void
    {
        $this->serve('anthropic-text');

        $this->anthropic()->stream(
            $this->conversation(),
            'claude-test-1',
            [],
            $this->collector($text, $calls, $usage, $error, $events),
        );

        self::assertSame('Hello there', $text);
        self::assertSame([], $calls);
        self::assertNotNull($usage);
        self::assertSame(25, $usage->inputTokens);
        self::assertSame(7, $usage->outputTokens);
        self::assertNull($error);
        self::assertSame(LlmEventType::Done, $events[array_key_last($events)]->type);
    }

    #[Test]
    public function anthropicSendsItsOwnShape(): void
    {
        $this->serve('anthropic-text');

        $this->anthropic()->stream(
            $this->conversation(),
            'claude-test-1',
            [new ToolDefinition('catalog_search', 'Search.', ['type' => 'object'])],
            $this->collector(),
        );

        [$request] = $this->requests();
        [$path] = [$request['path']];
        $body = $request['body'];

        self::assertSame('/messages', $path);
        self::assertContains('x-api-key: test-anthropic-key', $request['headers']);
        self::assertContains('anthropic-version: 2023-06-01', $request['headers']);

        self::assertSame('You are terse.', $body['system'], 'system is a top-level field');
        self::assertSame('say hi', $body['messages'][0]['content']);

        self::assertSame(
            ['name' => 'catalog_search', 'description' => 'Search.', 'input_schema' => ['type' => 'object']],
            $body['tools'][0],
            'a tool is bare, with no function wrapper',
        );

        self::assertTrue($body['stream']);
    }

    #[Test]
    public function anthropicMergesConsecutiveToolResultsIntoOneUserTurn(): void
    {
        $this->serve('anthropic-text');

        $conversation = new ConversationDTO([
            new MessageDTO(MessageRole::User, 'check both'),
            new MessageDTO(MessageRole::Assistant, 'checking', [
                new \PHPdot\Ai\Conversation\ToolCallDTO('toolu_a', 'catalog_search'),
                new \PHPdot\Ai\Conversation\ToolCallDTO('toolu_b', 'catalog_purge'),
            ]),
            new MessageDTO(MessageRole::Tool, 'a result', toolCallId: 'toolu_a', toolName: 'catalog_search'),
            new MessageDTO(MessageRole::Tool, 'b result', toolCallId: 'toolu_b', toolName: 'catalog_purge'),
        ]);

        $this->anthropic()->stream($conversation, 'claude-test-1', [], $this->collector());

        [$request] = $this->requests();
        $messages = $request['body']['messages'];

        self::assertCount(3, $messages, 'two results become ONE user turn');
        self::assertSame('user', $messages[2]['role']);
        self::assertSame('tool_result', $messages[2]['content'][0]['type']);
        self::assertSame('toolu_a', $messages[2]['content'][0]['tool_use_id']);
        self::assertSame('toolu_b', $messages[2]['content'][1]['tool_use_id']);

        self::assertSame('tool_use', $messages[1]['content'][1]['type']);
        self::assertStringContainsString(
            '"input":{}',
            (string) $request['raw'],
            'empty arguments encode as {}, never []',
        );
    }

    #[Test]
    public function anthropicAssemblesAToolCall(): void
    {
        $this->serve('anthropic-tool-call');

        $this->anthropic()->stream(
            $this->conversation(),
            'claude-test-1',
            [new ToolDefinition('catalog_search', 'Search.', ['type' => 'object'])],
            $this->collector($text, $calls),
        );

        self::assertCount(1, $calls);
        self::assertSame('toolu_01', $calls[0]->id);
        self::assertSame('catalog_search', $calls[0]->name);
        self::assertSame(['query' => 'kettle'], $calls[0]->arguments, 'partial_json fragments assembled and decoded');
    }

    #[Test]
    public function anthropicMidStreamFailureIsAnEventNotAThrow(): void
    {
        $this->serve('anthropic-error');

        /*
         * The callback REFUSES at the Error event — a reader who saw a failure
         * has no use for the tail — so nothing may follow it.
         */
        $this->anthropic()->stream(
            $this->conversation(),
            'claude-test-1',
            [],
            static function (LlmEvent $event) use (&$error, &$done): bool {
                if ($event->type === LlmEventType::Error) {
                    $error = $event->message;

                    return false;
                }

                $done = $done || $event->type === LlmEventType::Done;

                return true;
            },
        );

        self::assertSame('Overloaded.', $error);
        self::assertFalse($done, 'no Done follows a failure the reader refused');
    }

    #[Test]
    public function openaiStreamsTextAndUsage(): void
    {
        $this->serve('openai-text');

        $this->openai()->stream(
            $this->conversation(),
            'gpt-test',
            [],
            $this->collector($text, $calls, $usage, $error, $events),
        );

        self::assertSame('Hello', $text);
        self::assertSame(9, $usage?->inputTokens);
        self::assertSame(2, $usage?->outputTokens);
        self::assertSame(LlmEventType::Done, $events[array_key_last($events)]->type);
    }

    #[Test]
    public function openaiSendsItsOwnShape(): void
    {
        $this->serve('openai-text');

        $this->openai()->stream(
            $this->conversation(),
            'gpt-test',
            [new ToolDefinition('catalog_search', 'Search.', ['type' => 'object'])],
            $this->collector(),
        );

        [$request] = $this->requests();
        $body = $request['body'];

        self::assertSame('/chat/completions', $request['path']);
        self::assertContains('Authorization: Bearer test-openai-key', $request['headers']);

        self::assertSame('system', $body['messages'][0]['role'], 'system is the first message');
        self::assertSame('You are terse.', $body['messages'][0]['content']);

        self::assertSame('function', $body['tools'][0]['type']);
        self::assertSame('catalog_search', $body['tools'][0]['function']['name']);
        self::assertTrue($body['stream_options']['include_usage']);
    }

    #[Test]
    public function openaiSpellsToolResultsAsToolMessages(): void
    {
        $this->serve('openai-text');

        $conversation = new ConversationDTO([
            new MessageDTO(MessageRole::User, 'check'),
            new MessageDTO(MessageRole::Assistant, '', [
                new \PHPdot\Ai\Conversation\ToolCallDTO('call_1', 'catalog_search', ['query' => 'x']),
            ]),
            new MessageDTO(MessageRole::Tool, 'the result', toolCallId: 'call_1', toolName: 'catalog_search'),
        ]);

        $this->openai()->stream($conversation, 'gpt-test', [], $this->collector());

        [$request] = $this->requests();
        $messages = $request['body']['messages'];

        self::assertSame('user', $messages[0]['role'], 'no system in this thread, so the turn starts at user');
        self::assertSame('tool', $messages[2]['role']);
        self::assertSame('call_1', $messages[2]['tool_call_id']);

        $sent = $messages[1]['tool_calls'][0];

        self::assertSame('call_1', $sent['id']);
        self::assertSame('{"query":"x"}', $sent['function']['arguments'], 'arguments ride as a JSON string');
        self::assertNull($messages[1]['content'], 'an assistant turn with only calls carries null content');
    }

    #[Test]
    public function openaiAssemblesAToolCallWithIdentityOnTheFirstFragmentOnly(): void
    {
        $this->serve('openai-tool-call');

        $this->openai()->stream(
            $this->conversation(),
            'gpt-test',
            [new ToolDefinition('catalog_search', 'Search.', ['type' => 'object'])],
            $this->collector($text, $calls, $usage),
        );

        self::assertCount(1, $calls);
        self::assertSame('call_1', $calls[0]->id);
        self::assertSame('catalog_search', $calls[0]->name);
        self::assertSame(['query' => 'kettle'], $calls[0]->arguments);
    }

    #[Test]
    public function abandonmentSkipsTheDriversTail(): void
    {
        $this->serve('openai-text');

        $events = [];
        $seenText = false;

        $this->openai()->stream(
            $this->conversation(),
            'gpt-test',
            [],
            static function (LlmEvent $event) use (&$events, &$seenText): bool {
                $events[] = $event->type;

                if ($event->type === LlmEventType::Delta) {
                    $seenText = true;

                    return false;
                }

                return true;
            },
        );

        self::assertTrue($seenText, 'the turn began before it was abandoned');
        self::assertCount(1, $events, 'one Delta is all a reader who left immediately gets');

        foreach ($events as $type) {
            self::assertNotSame(LlmEventType::Done, $type, 'no Done is reported to a reader who left');
        }
    }

    /**
     * @return ConversationDTO
     */
    private function conversation(): ConversationDTO
    {
        return new ConversationDTO(
            [new MessageDTO(MessageRole::User, 'say hi')],
            'You are terse.',
        );
    }

    /**
     * @param string|null $text Assembled answer text
     * @param list<\PHPdot\Ai\Conversation\ToolCallDTO>|null $calls Assembled calls
     * @param \PHPdot\Ai\Event\LlmUsage|null $usage Turn cost
     * @param string|null $error Mid-stream failure
     * @param list<LlmEvent>|null $events Every event, in order
     *
     * @return \Closure(LlmEvent): bool
     */
    private function collector(
        null|string &$text = null,
        null|array &$calls = null,
        null|\PHPdot\Ai\Event\LlmUsage &$usage = null,
        null|string &$error = null,
        null|array &$events = null,
    ): \Closure {
        $text = '';
        $calls = [];
        $events = [];

        return static function (LlmEvent $event) use (&$text, &$calls, &$usage, &$error, &$events): bool {
            $events[] = $event;

            match ($event->type) {
                LlmEventType::Delta    => $text .= $event->text,
                LlmEventType::ToolCall => $calls[] = $event->toolCall,
                LlmEventType::Done     => $usage = $event->usage,
                LlmEventType::Error    => $error = $event->message,
            };

            return true;
        };
    }

    private function anthropic(): AnthropicDriver
    {
        return new AnthropicDriver(
            new StreamingHttp(10),
            $this->baseUrl(),
            'test-anthropic-key',
            '2023-06-01',
            64,
        );
    }

    private function openai(): OpenAiDriver
    {
        return new OpenAiDriver(
            new StreamingHttp(10),
            $this->baseUrl(),
            'test-openai-key',
            64,
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
