<?php

declare(strict_types=1);

/**
 * Every provider that speaks OpenAI's /chat/completions.
 *
 * Which is most of them: OpenAI, Groq, DeepSeek, Together, Fireworks, OpenRouter,
 * Ollama, and Gemini's compatibility endpoint. They differ by base URL and model name,
 * and that is configuration.
 *
 * HOW THIS SHAPE SPELLS A TOOL, and why none of it leaks upward:
 *
 *   - the system instruction is the FIRST MESSAGE, where Anthropic has a top-level field
 *   - a tool is `{type: function, function: {name, description, parameters}}`
 *   - a call streams as `delta.tool_calls[]`, `id` and `function.name` on the first
 *     fragment only, `function.arguments` as partial JSON after
 *   - a RESULT is a message with `role: tool` and a `tool_call_id`, where Anthropic
 *     puts a `tool_result` block inside a USER message
 *
 * `stream_options.include_usage` asks for a final chunk carrying token counts.
 * Compatible endpoints frequently ignore it, which is why LlmUsage treats zero as NOT
 * REPORTED rather than as free.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Bridge\OpenAi;

use Closure;
use PHPdot\Ai\Bridge\ToolCallAssembler;
use PHPdot\Ai\Contract\LlmCapabilities;
use PHPdot\Ai\Contract\LlmDriver;
use PHPdot\Ai\Contract\ToolDefinition;
use PHPdot\Ai\Conversation\ConversationDTO;
use PHPdot\Ai\Conversation\MessageDTO;
use PHPdot\Ai\Conversation\MessageRole;
use PHPdot\Ai\Event\LlmEvent;
use PHPdot\Ai\Event\LlmUsage;
use PHPdot\Ai\Internal\Values;
use PHPdot\Ai\Registry\LlmShape;
use PHPdot\Ai\Transport\Json;
use PHPdot\Ai\Transport\StreamingHttp;

final readonly class Driver implements LlmDriver
{
    /**
     * @param StreamingHttp $http The shared streaming POST
     * @param string $baseUrl The provider's API root, no trailing slash
     * @param string $apiKey The bearer token
     * @param int $maxTokens Ceiling on one answer
     * @param string $maxTokensKey The wire name of the ceiling: OpenAI's reasoning family rejects
     *                             `max_tokens` outright, so the default is the name every current
     *                             model accepts; a compatible vendor that never moved off the old
     *                             name overrides it in its provider block
     */
    public function __construct(
        private StreamingHttp $http,
        private string $baseUrl,
        private string $apiKey,
        private int $maxTokens,
        private string $maxTokensKey = 'max_completion_tokens',
    ) {}

    /**
     * @inheritDoc
     */
    public function shape(): LlmShape
    {
        return LlmShape::OpenAi;
    }

    /**
     * @inheritDoc
     */
    public function capabilities(): LlmCapabilities
    {
        return new LlmCapabilities(
            streaming: true,
            systemPrompt: true,
            usageReporting: true,
            toolUse: true,
        );
    }

    /**
     * @inheritDoc
     */
    public function stream(ConversationDTO $conversation, string $model, array $tools, Closure $onEvent): void
    {
        $usage = new LlmUsage();
        $assembler = new ToolCallAssembler();

        $payload = [
            'model'          => $model,
            $this->maxTokensKey => $this->maxTokens,
            'stream'         => true,
            'stream_options' => ['include_usage' => true],
            'messages'       => $this->messages($conversation),
        ];

        if ($tools !== []) {
            $payload['tools'] = array_map(
                static fn(ToolDefinition $tool): array => [
                    'type'     => 'function',
                    'function' => [
                        'name'        => $tool->name,
                        'description' => $tool->description,
                        'parameters'  => $tool->parameters,
                    ],
                ],
                $tools,
            );
        }

        $completed = $this->http->post(
            rtrim($this->baseUrl, '/') . '/chat/completions',
            $payload,
            ['Authorization: Bearer ' . $this->apiKey],
            static function (string $data, null|string $event) use ($onEvent, $assembler, &$usage): bool {
                if ($data === '[DONE]') {
                    return true;
                }

                $frame = Json::decode($data);

                if ($frame === null) {
                    return true;
                }

                if (Json::get($frame, ['usage']) !== null) {
                    $usage = new LlmUsage(
                        Values::int(Json::get($frame, ['usage', 'prompt_tokens'])),
                        Values::int(Json::get($frame, ['usage', 'completion_tokens'])),
                    );
                }

                $calls = Json::get($frame, ['choices', 0, 'delta', 'tool_calls']);

                if (is_array($calls)) {
                    foreach ($calls as $call) {
                        $index = Values::int(Json::get($call, ['index']));

                        $assembler->open(
                            $index,
                            Values::string(Json::get($call, ['id'])),
                            Values::string(Json::get($call, ['function', 'name'])),
                        );

                        $assembler->append($index, Values::string(Json::get($call, ['function', 'arguments']), trim: false));
                    }

                    return true;
                }

                $text = Values::string(Json::get($frame, ['choices', 0, 'delta', 'content']), trim: false);

                if ($text !== '') {
                    return $onEvent(LlmEvent::delta($text));
                }

                return true;
            },
        );

        /*
         * A consumer who stopped reading is told nothing further — not the calls
         * that assembled, not the Done that never happened for them.
         */
        if ($completed === false) {
            return;
        }

        foreach ($assembler->flush() as $call) {
            if ($onEvent(LlmEvent::toolCall($call)) === false) {
                return;
            }
        }

        $onEvent(LlmEvent::done($usage));
    }

    /**
     * The thread as this shape wants it: system inline at the front, results as their
     * own `tool` messages.
     *
     * @param ConversationDTO $conversation The thread
     *
     * @return list<array<string, mixed>>
     */
    private function messages(ConversationDTO $conversation): array
    {
        $messages = [];

        if ($conversation->system !== null && $conversation->system !== '') {
            $messages[] = ['role' => MessageRole::System->value, 'content' => $conversation->system];
        }

        foreach ($conversation->messages as $message) {
            $messages[] = $this->message($message);
        }

        return $messages;
    }

    /**
     * One message, in this shape's spelling.
     *
     * @param MessageDTO $message The message
     *
     * @return array<string, mixed>
     */
    private function message(MessageDTO $message): array
    {
        if ($message->role === MessageRole::Tool) {
            return [
                'role'         => 'tool',
                'tool_call_id' => $message->toolCallId ?? '',
                'content'      => $message->content,
            ];
        }

        if ($message->toolCalls === []) {
            return ['role' => $message->role->value, 'content' => $message->content];
        }

        return [
            'role'    => $message->role->value,
            'content' => $message->content === '' ? null : $message->content,

            'tool_calls' => array_map(
                static fn($call): array => [
                    'id'       => $call->id,
                    'type'     => 'function',
                    'function' => [
                        'name'      => $call->name,
                        'arguments' => json_encode($call->arguments, JSON_THROW_ON_ERROR),
                    ],
                ],
                $message->toolCalls,
            ),
        ];
    }
}
