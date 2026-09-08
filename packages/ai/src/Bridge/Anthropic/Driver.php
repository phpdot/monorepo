<?php

declare(strict_types=1);

/**
 * Anthropic's /messages — the one wire shape that is not OpenAI's.
 *
 * The differences worth naming, because they are the whole implementation:
 *
 *   - the key rides as `x-api-key`, not a bearer, and `max_tokens` is required
 *   - the system instruction is a TOP-LEVEL field, not a message
 *   - a tool is `{name, description, input_schema}` — no `function` wrapper
 *   - a call streams as a `tool_use` CONTENT BLOCK: `content_block_start` carries its
 *     id and name, `content_block_delta` carries `input_json_delta.partial_json`, and
 *     `content_block_stop` closes it. OpenAI has no per-call end marker at all.
 *   - a RESULT is a `tool_result` block inside a USER message, where OpenAI has a
 *     `tool` role. This is the disagreement that makes a fourth canonical role
 *     necessary rather than cosmetic.
 *
 * Its stream is genuinely event-named where OpenAI's is one anonymous chunk shape.
 * Input tokens arrive early on `message_start`; output tokens only settle on
 * `message_delta`, so both are kept and reported together at the end.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Bridge\Anthropic;

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
     * @param string $baseUrl The API root, no trailing slash
     * @param string $apiKey The key
     * @param string $version The anthropic-version header
     * @param int $maxTokens Ceiling on one answer
     */
    public function __construct(
        private StreamingHttp $http,
        private string $baseUrl,
        private string $apiKey,
        private string $version,
        private int $maxTokens,
    ) {}

    /**
     * @inheritDoc
     */
    public function shape(): LlmShape
    {
        return LlmShape::Anthropic;
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
        $inputTokens = 0;
        $outputTokens = 0;
        $assembler = new ToolCallAssembler();
        $ready = [];

        $payload = [
            'model'      => $model,
            'max_tokens' => $this->maxTokens,
            'stream'     => true,
            'messages'   => $this->messages($conversation),
        ];

        if ($conversation->system !== null && $conversation->system !== '') {
            $payload['system'] = $conversation->system;
        }

        if ($tools !== []) {
            $payload['tools'] = array_map(
                static fn(ToolDefinition $tool): array => [
                    'name'         => $tool->name,
                    'description'  => $tool->description,
                    'input_schema' => $tool->parameters,
                ],
                $tools,
            );
        }

        $completed = $this->http->post(
            rtrim($this->baseUrl, '/') . '/messages',
            $payload,
            [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: ' . $this->version,
            ],
            static function (string $data, null|string $event) use (
                $onEvent,
                $assembler,
                &$ready,
                &$inputTokens,
                &$outputTokens,
            ): bool {
                $frame = Json::decode($data);

                if ($frame === null) {
                    return true;
                }

                $type = $event ?? Values::string(Json::get($frame, ['type']));

                if ($type === 'message_start') {
                    $inputTokens = Values::int(Json::get($frame, ['message', 'usage', 'input_tokens']));

                    return true;
                }

                if ($type === 'message_delta') {
                    $reported = Json::get($frame, ['usage', 'output_tokens']);
                    $outputTokens = $reported === null ? $outputTokens : Values::int($reported);

                    return true;
                }

                if ($type === 'error') {
                    $reason = Values::string(Json::get($frame, ['error', 'message']), 'The model stopped mid-answer.');

                    return $onEvent(LlmEvent::error($reason));
                }

                if ($type === 'content_block_start') {
                    if (Values::string(Json::get($frame, ['content_block', 'type'])) === 'tool_use') {
                        $assembler->open(
                            Values::int(Json::get($frame, ['index'])),
                            Values::string(Json::get($frame, ['content_block', 'id'])),
                            Values::string(Json::get($frame, ['content_block', 'name'])),
                        );
                    }

                    return true;
                }

                if ($type === 'content_block_stop') {
                    $call = $assembler->close(Values::int(Json::get($frame, ['index'])));

                    if ($call !== null) {
                        $ready[] = $call;
                    }

                    return true;
                }

                if ($type !== 'content_block_delta') {
                    return true;
                }

                $partial = Json::get($frame, ['delta', 'partial_json']);

                if ($partial !== null) {
                    $assembler->append(Values::int(Json::get($frame, ['index'])), Values::string($partial, trim: false));

                    return true;
                }

                $text = Values::string(Json::get($frame, ['delta', 'text']), trim: false);

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

        foreach ([...$ready, ...$assembler->flush()] as $call) {
            if ($onEvent(LlmEvent::toolCall($call)) === false) {
                return;
            }
        }

        $onEvent(LlmEvent::done(new LlmUsage($inputTokens, $outputTokens)));
    }

    /**
     * The thread as this shape wants it: results as `tool_result` blocks inside a user
     * message, consecutive ones merged because the API takes one user turn per
     * assistant turn and not one per result.
     *
     * @param ConversationDTO $conversation The thread
     *
     * @return list<array<string, mixed>>
     */
    private function messages(ConversationDTO $conversation): array
    {
        $messages = [];
        $results = [];

        foreach ($conversation->messages as $message) {
            if ($message->role === MessageRole::Tool) {
                $results[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $message->toolCallId ?? '',
                    'content'     => $message->content,
                ];

                continue;
            }

            if ($results !== []) {
                $messages[] = ['role' => MessageRole::User->value, 'content' => $results];
                $results = [];
            }

            $messages[] = $this->message($message);
        }

        if ($results !== []) {
            $messages[] = ['role' => MessageRole::User->value, 'content' => $results];
        }

        return $messages;
    }

    /**
     * One non-result message, in this shape's spelling.
     *
     * @param MessageDTO $message The message
     *
     * @return array<string, mixed>
     */
    private function message(MessageDTO $message): array
    {
        if ($message->toolCalls === []) {
            return ['role' => $message->role->value, 'content' => $message->content];
        }

        $blocks = [];

        if ($message->content !== '') {
            $blocks[] = ['type' => 'text', 'text' => $message->content];
        }

        foreach ($message->toolCalls as $call) {
            $blocks[] = [
                'type'  => 'tool_use',
                'id'    => $call->id,
                'name'  => $call->name,
                'input' => $call->arguments === [] ? new \stdClass() : $call->arguments,
            ];
        }

        return ['role' => $message->role->value, 'content' => $blocks];
    }
}
