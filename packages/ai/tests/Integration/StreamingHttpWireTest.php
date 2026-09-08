<?php

declare(strict_types=1);

namespace PHPdot\Ai\Tests\Integration;

use PHPdot\Ai\Exception\LlmError;
use PHPdot\Ai\Tests\Support\FakeProvider\ProviderHarness;
use PHPdot\Ai\Transport\StreamingHttp;
use PHPUnit\Framework\Attributes\Test;

/**
 * The transport against a real socket: frames arrive as they land, the status
 * is captured off the real status line, refusals are said in one line, and
 * abandonment stops the transfer instead of outliving the reader.
 */
final class StreamingHttpWireTest extends ProviderHarness
{
    #[Test]
    public function framesArriveIncrementally(): void
    {
        $this->serve('openai-dribble');

        $deliveries = 0;

        $completed = (new StreamingHttp(10))->post(
            $this->baseUrl() . '/chat/completions',
            ['model' => 'test'],
            ['Authorization: Bearer test'],
            static function (string $data) use (&$deliveries): bool {
                $deliveries++;

                return true;
            },
        );

        self::assertTrue($completed);
        self::assertGreaterThan(
            2,
            $deliveries,
            'a 7-byte dribble must reach the frame callback as multiple deliveries, not one buffered blob',
        );
    }

    #[Test]
    public function aRefusalWithAReasonCarriesIt(): void
    {
        $this->serve('http-401-reason');

        $http = new StreamingHttp(10);

        $this->expectException(LlmError::class);
        $this->expectExceptionMessage('Incorrect API key provided.');

        $http->post($this->baseUrl() . '/chat/completions', [], [], static fn(): bool => true);
    }

    #[Test]
    public function aPlainRefusalIsSaidByStatus(): void
    {
        $this->serve('http-401-plain');

        try {
            (new StreamingHttp(10))->post($this->baseUrl() . '/chat/completions', [], [], static fn(): bool => true);

            self::fail('A plain 401 did not refuse.');
        } catch (LlmError $error) {
            self::assertSame('The provider rejected the API key.', $error->getMessage());
        }
    }

    #[Test]
    public function rateLimitingIsSaidInOneLine(): void
    {
        $this->serve('http-429');

        try {
            (new StreamingHttp(10))->post($this->baseUrl() . '/chat/completions', [], [], static fn(): bool => true);

            self::fail('A 429 did not refuse.');
        } catch (LlmError $error) {
            self::assertSame('The provider is rate limiting this key.', $error->getMessage());
        }
    }

    #[Test]
    public function anOutageIsSaidInOneLine(): void
    {
        $this->serve('http-500');

        try {
            (new StreamingHttp(10))->post($this->baseUrl() . '/chat/completions', [], [], static fn(): bool => true);

            self::fail('A 500 did not refuse.');
        } catch (LlmError $error) {
            self::assertSame('The provider is not answering.', $error->getMessage());
        }
    }

    #[Test]
    public function aTimeoutIsSaidInSeconds(): void
    {
        $this->serve('hold');

        $started = hrtime(true);

        try {
            (new StreamingHttp(1))->post($this->baseUrl() . '/chat/completions', [], [], static fn(): bool => true);

            self::fail('A held connection did not time out.');
        } catch (LlmError $error) {
            self::assertSame('The model took longer than 1 seconds to answer.', $error->getMessage());
            self::assertGreaterThan(0.9, (hrtime(true) - $started) / 1e9);
        }
    }

    #[Test]
    public function abandonmentStopsTheTransferPromptly(): void
    {
        $this->serve('slow');

        $seen = 0;
        $started = hrtime(true);

        $completed = (new StreamingHttp(10))->post(
            $this->baseUrl() . '/chat/completions',
            ['model' => 'test'],
            [],
            static function (string $data) use (&$seen): bool {
                $seen++;

                return $seen < 3;
            },
        );

        $elapsed = (hrtime(true) - $started) / 1e9;

        self::assertFalse($completed, 'abandonment must be answered, not swallowed');
        self::assertLessThan(1.2, $elapsed, "the transfer kept running after the reader left ({$elapsed}s)");
    }

    /**
     * @return string
     */
    protected function scenario(): string
    {
        return 'openai-text';
    }
}
