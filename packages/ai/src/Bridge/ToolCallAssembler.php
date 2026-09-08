<?php

declare(strict_types=1);

/**
 * Reassembles a streamed tool call from the fragments it arrives in.
 *
 * THE SINGLE NASTIEST PART OF SPEAKING TO TWO PROVIDERS, and the same problem twice, so
 * it is solved once here.
 *
 * Both wire shapes stream a tool call's arguments as PARTIAL JSON TEXT, keyed by an
 * index, and that text is invalid at every point except the last:
 *
 *   OpenAI      choices[0].delta.tool_calls[] — `index`, then `id` and
 *               `function.name` on the first fragment only, then
 *               `function.arguments` in pieces. No per-call end marker;
 *               the turn's `finish_reason` is the only signal.
 *   Anthropic   content_block_start carries `index`, `id` and `name`;
 *               content_block_delta carries `input_json_delta.partial_json`;
 *               content_block_stop closes that index explicitly.
 *
 * So the shared shape is: open an index with its identity, append text to it, and
 * decode when told the call is done. A call whose text never becomes valid JSON is
 * DROPPED rather than guessed at — a half-decoded argument object would be handed to a
 * tool as though the model had meant it, and the tool would answer a question nobody
 * asked.
 *
 * An empty argument string decodes to an empty array, not a failure: a tool with no
 * required arguments is legitimately called with `{}`, and some providers send nothing
 * at all rather than two braces.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Bridge;

use PHPdot\Ai\Conversation\ToolCallDTO;

final class ToolCallAssembler
{
    /** @var array<int, array{id: string, name: string, json: string}> */
    private array $pending = [];

    /**
     * Begin a call at an index, or fill in identity that arrived late.
     *
     * OpenAI sends `id` and `name` on the first fragment of a call and omits them on
     * every fragment after; a second open for the same index must therefore not erase
     * what the first one learned.
     *
     * @param int $index Which call in the turn
     * @param string $id The provider's id, when known
     * @param string $name The tool's name, when known
     */
    public function open(int $index, string $id = '', string $name = ''): void
    {
        $call = $this->pending[$index] ?? ['id' => '', 'name' => '', 'json' => ''];

        if ($id !== '') {
            $call['id'] = $id;
        }

        if ($name !== '') {
            $call['name'] = $name;
        }

        $this->pending[$index] = $call;
    }

    /**
     * Add a fragment of an index's argument text.
     *
     * @param int $index Which call
     * @param string $fragment The piece that just arrived
     */
    public function append(int $index, string $fragment): void
    {
        if ($fragment === '') {
            return;
        }

        $this->open($index);
        $this->pending[$index]['json'] .= $fragment;
    }

    /**
     * Close one index and hand back the call, if it assembled into one.
     *
     * @param int $index Which call
     *
     * @return ?ToolCallDTO Null when the index was never opened or its text is unusable
     */
    public function close(int $index): null|ToolCallDTO
    {
        $call = $this->pending[$index] ?? null;
        unset($this->pending[$index]);

        return $call === null ? null : $this->build($call);
    }

    /**
     * Close every index still open, in the order they were opened.
     *
     * @return list<ToolCallDTO>
     */
    public function flush(): array
    {
        $calls = [];

        foreach (array_keys($this->pending) as $index) {
            $call = $this->close($index);

            if ($call !== null) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    /**
     * Whether anything is still open.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->pending === [];
    }

    /**
     * One accumulated call, decoded.
     *
     * @param array{id: string, name: string, json: string} $call What accumulated
     *
     * @return ?ToolCallDTO Null when it cannot be trusted
     */
    private function build(array $call): null|ToolCallDTO
    {
        if ($call['name'] === '') {
            return null;
        }

        $text = trim($call['json']);

        if ($text === '') {
            return new ToolCallDTO($call['id'], $call['name']);
        }

        $arguments = json_decode($text, true);

        if (!is_array($arguments)) {
            return null;
        }

        /** @var array<string, mixed> $arguments */
        return new ToolCallDTO($call['id'], $call['name'], $arguments);
    }
}
