<?php

declare(strict_types=1);

namespace PHPdot\Ai\Tests\Integration\Live;

use PHPdot\Ai\Bridge\OpenAi\Driver as OpenAiDriver;
use PHPdot\Ai\Conversation\ConversationDTO;
use PHPdot\Ai\Conversation\MessageDTO;
use PHPdot\Ai\Conversation\MessageRole;
use PHPdot\Ai\Event\LlmEvent;
use PHPdot\Ai\Event\LlmEventType;
use PHPdot\Ai\Transport\StreamingHttp;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ONE real streaming turn against OpenAI — the whole stack, real SSE, real
 * usage accounting. Excluded from every keyless run (the `live` group is
 * excluded at the root, and the suite is selected by name when wanted):
 *
 *   OPENAI_API_KEY=<the key> vendor/bin/phpunit -c packages/ai/phpunit.xml --testsuite Live
 *
 * The key is read from the environment at run time and is never written
 * anywhere — not into this file, the configuration, a fixture, or the log.
 */
#[Group('live')]
final class OpenAiLiveTest extends TestCase
{
    #[Test]
    public function oneTinyTurnStreamsEndToEnd(): void
    {
        $apiKey = (string) getenv('OPENAI_API_KEY');

        if ($apiKey === '') {
            self::markTestSkipped('OPENAI_API_KEY is not set; the live suite is opt-in.');
        }

        $model = (string) (getenv('OPENAI_LIVE_MODEL') ?: 'gpt-4o-mini');

        $text = '';
        $usage = null;
        $deltas = 0;

        (new OpenAiDriver(
            new StreamingHttp(60),
            'https://api.openai.com/v1',
            $apiKey,
            32,
        ))->stream(
            new ConversationDTO(
                [new MessageDTO(MessageRole::User, 'Reply with the single word: ok')],
                'You are terse.',
            ),
            $model,
            [],
            static function (LlmEvent $event) use (&$text, &$usage, &$deltas): bool {
                if ($event->type === LlmEventType::Delta) {
                    $text .= $event->text;
                    $deltas++;
                }

                if ($event->type === LlmEventType::Done) {
                    $usage = $event->usage;
                }

                return true;
            },
        );

        self::assertGreaterThan(0, $deltas, 'the answer arrived as a stream, not one blob');
        self::assertNotSame('', $text);
        self::assertNotNull($usage);
        self::assertGreaterThan(0, $usage->inputTokens, 'the provider reported real usage');
    }
}
