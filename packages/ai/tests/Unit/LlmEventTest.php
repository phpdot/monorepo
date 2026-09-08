<?php

declare(strict_types=1);

namespace PHPdot\Ai\Tests\Unit;

use PHPdot\Ai\Conversation\ToolCallDTO;
use PHPdot\Ai\Event\LlmEvent;
use PHPdot\Ai\Event\LlmEventType;
use PHPdot\Ai\Event\LlmUsage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LlmEventTest extends TestCase
{
    #[Test]
    public function eachFactoryCarriesExactlyItsPayload(): void
    {
        $delta = LlmEvent::delta('Hel');

        self::assertSame(LlmEventType::Delta, $delta->type);
        self::assertSame('Hel', $delta->text);
        self::assertNull($delta->toolCall);

        $call = new ToolCallDTO('id-1', 'catalog_search', ['query' => 'kettle']);
        $toolCall = LlmEvent::toolCall($call);

        self::assertSame(LlmEventType::ToolCall, $toolCall->type);
        self::assertSame($call, $toolCall->toolCall);

        $done = LlmEvent::done(new LlmUsage(11, 22));

        self::assertSame(LlmEventType::Done, $done->type);
        self::assertSame(11, $done->usage?->inputTokens);
        self::assertSame(22, $done->usage?->outputTokens);

        $error = LlmEvent::error('overloaded');

        self::assertSame(LlmEventType::Error, $error->type);
        self::assertSame('overloaded', $error->message);
    }
}
