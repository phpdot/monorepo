<?php

declare(strict_types=1);

namespace PHPdot\Totp\Tests\Unit;

use PHPdot\Totp\Enum\Algorithm;
use PHPdot\Totp\Exception\InvalidParameterException;
use PHPdot\Totp\Otp\Totp;
use PHPdot\Totp\Secret\Secret;
use PHPdot\Totp\Tests\Support\FrozenClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    private const string SEED = '12345678901234567890';
    private const int FROZEN = 1111111111;

    public function test_at_matches_rfc_value(): void
    {
        // RFC 6238: SHA1 seed, 8 digits, T=59 -> 94287082.
        $totp = new Totp(new Secret(self::SEED), Algorithm::Sha1, 8);

        self::assertSame('94287082', $totp->at(59));
    }

    public function test_current_previous_next_against_frozen_clock(): void
    {
        $totp = $this->frozen();

        // 6-digit form of the RFC 8-digit value 14050471 at T=1111111111.
        self::assertSame('050471', $totp->current());
        self::assertSame($totp->at(self::FROZEN - 30), $totp->previous());
        self::assertSame($totp->at(self::FROZEN + 30), $totp->next());
        self::assertNotSame($totp->current(), $totp->next());
    }

    public function test_window_codes_align_with_helpers(): void
    {
        $totp = $this->frozen();
        $window = $totp->window(1);

        self::assertSame($totp->previous(), $window->previous());
        self::assertSame($totp->current(), $window->current());
        self::assertSame($totp->next(), $window->next());
        self::assertCount(3, $window->all());
    }

    public function test_window_can_span_multiple_steps(): void
    {
        self::assertCount(5, $this->frozen()->window(2)->all());
    }

    public function test_verify_accepts_current_and_returns_matched_step(): void
    {
        $totp = $this->frozen();

        $result = $totp->verify($totp->current());

        self::assertTrue($result->passed);
        self::assertSame(intdiv(self::FROZEN, 30), $result->timestep);
    }

    public function test_verify_accepts_neighbouring_step_within_window(): void
    {
        $totp = $this->frozen();

        self::assertTrue($totp->verify($totp->previous())->passed);
        self::assertTrue($totp->verify($totp->next())->passed);
    }

    public function test_verify_rejects_wrong_code(): void
    {
        self::assertFalse($this->frozen()->verify('000000')->passed);
    }

    public function test_after_blocks_replay_of_used_step(): void
    {
        $totp = $this->frozen();
        $used = intdiv(self::FROZEN, 30);

        // Replaying the just-used current step is rejected once `after` is set.
        $result = $totp->verify($totp->current(), after: $used);

        self::assertFalse($result->passed);
    }

    public function test_period_must_be_positive(): void
    {
        $this->expectException(InvalidParameterException::class);

        new Totp(new Secret(self::SEED), Algorithm::Sha1, 6, 0);
    }

    public function test_negative_window_throws(): void
    {
        $this->expectException(InvalidParameterException::class);

        $this->frozen()->verify('000000', null, -1);
    }

    public function test_honours_non_zero_epoch(): void
    {
        $epoch = 1_000_000_000;
        $totp = new Totp(new Secret(self::SEED), Algorithm::Sha1, 6, 30, new FrozenClock($epoch + 90), $epoch);

        $result = $totp->verify($totp->current());

        self::assertTrue($result->passed);
        self::assertSame(3, $result->timestep, '90s / 30s after epoch = step 3');
    }

    public function test_seven_digit_codes_roundtrip(): void
    {
        $totp = new Totp(new Secret(self::SEED), Algorithm::Sha1, 7, 30, new FrozenClock(self::FROZEN));

        self::assertSame(7, strlen($totp->current()));
        self::assertTrue($totp->verify($totp->current())->passed);
    }

    public function test_custom_period_roundtrips(): void
    {
        $totp = new Totp(new Secret(self::SEED), Algorithm::Sha256, 8, 60, new FrozenClock(self::FROZEN));

        $result = $totp->verify($totp->current());

        self::assertTrue($result->passed);
        self::assertSame(intdiv(self::FROZEN, 60), $result->timestep);
    }

    public function test_window_zero_rejects_neighbours(): void
    {
        $totp = $this->frozen();

        self::assertTrue($totp->verify($totp->current(), null, 0)->passed);
        self::assertFalse($totp->verify($totp->previous(), null, 0)->passed);
        self::assertFalse($totp->verify($totp->next(), null, 0)->passed);
    }

    #[DataProvider('malformedProvider')]
    public function test_malformed_codes_are_rejected_without_error(string $garbage): void
    {
        self::assertFalse($this->frozen()->verify($garbage)->passed);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'letters' => ['abcdef'];
        yield 'too short' => ['12'];
        yield 'too long' => ['1234567890'];
    }

    private function frozen(): Totp
    {
        return new Totp(new Secret(self::SEED), Algorithm::Sha1, 6, 30, new FrozenClock(self::FROZEN));
    }
}
