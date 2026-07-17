<?php

declare(strict_types=1);

namespace PHPdot\Error\Tests\Integration;

use PHPdot\Error\ErrorBag;
use PHPdot\Error\ErrorEntry;
use PHPdot\Error\ErrorType;
use PHPdot\Error\Tests\Fixtures\OrderErrors;
use PHPdot\Error\Tests\Fixtures\UserErrors;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FullFlowTest extends TestCase
{
    #[Test]
    public function it_simulates_registration_validation(): void
    {
        $errors = $this->simulateRegistration('bad-email', 'short');

        self::assertTrue($errors->hasErrors());
        self::assertSame(2, $errors->count());
        self::assertTrue($errors->hasError(UserErrors::INVALID_EMAIL));
        self::assertTrue($errors->hasError(UserErrors::WEAK_PASSWORD));
        self::assertSame(422, $errors->getHttpStatus());

        $emailErrors = $errors->forContext('email');
        self::assertCount(1, $emailErrors);
        self::assertSame('errors.user.invalid_email', $emailErrors[0]->description);

        $passwordErrors = $errors->forContext('password');
        self::assertCount(1, $passwordErrors);
        self::assertSame(['min' => 8], $passwordErrors[0]->params);
    }

    #[Test]
    public function it_simulates_successful_registration(): void
    {
        $errors = $this->simulateRegistration('valid@example.com', 'StrongPassword123');

        self::assertFalse($errors->hasErrors());
        self::assertSame(0, $errors->count());
    }

    #[Test]
    public function it_simulates_duplicate_email(): void
    {
        $errors = $this->simulateRegistration('taken@example.com', 'StrongPassword123');
        // Simulate the DB returning a duplicate
        $errors->add(UserErrors::EMAIL_TAKEN, 'email');

        self::assertTrue($errors->hasErrors());
        self::assertTrue($errors->hasError(UserErrors::EMAIL_TAKEN));
        self::assertSame(409, $errors->getHttpStatus());
    }

    #[Test]
    public function it_serializes_to_json_api_format(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::INVALID_EMAIL, 'email');
        $bag->add(UserErrors::WEAK_PASSWORD, 'password', ['min' => 8]);

        $json = json_encode(['errors' => $bag->toArray()], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('errors', $decoded);
        self::assertCount(2, $decoded['errors']);

        $first = $decoded['errors'][0];
        self::assertSame('00010003', $first['code']);
        self::assertSame('Invalid email address', $first['message']);
        self::assertSame('errors.user.invalid_email', $first['description']);
        self::assertSame('validation', $first['type']);
        self::assertSame(422, $first['httpStatus']);
        self::assertSame('email', $first['context']);
        self::assertSame([], $first['params']);

        $second = $decoded['errors'][1];
        self::assertSame('00010004', $second['code']);
        self::assertSame(['min' => 8], $second['params']);
    }

    #[Test]
    public function it_merges_errors_from_sub_operations(): void
    {
        $userErrors = new ErrorBag();
        $userErrors->add(UserErrors::INVALID_EMAIL, 'email');

        $orderErrors = new ErrorBag();
        $orderErrors->add(OrderErrors::PAYMENT_FAILED, 'stripe');

        $combined = new ErrorBag();
        $combined->merge($userErrors)->merge($orderErrors);

        self::assertSame(2, $combined->count());
        self::assertTrue($combined->hasError(UserErrors::INVALID_EMAIL));
        self::assertTrue($combined->hasError(OrderErrors::PAYMENT_FAILED));

        $validation = $combined->ofType(ErrorType::VALIDATION);
        self::assertCount(1, $validation);

        $server = $combined->ofType(ErrorType::SERVER);
        self::assertCount(1, $server);
    }

    #[Test]
    public function it_filters_and_groups_for_frontend(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::INVALID_EMAIL, 'email');
        $bag->add(UserErrors::EMAIL_TAKEN, 'email');
        $bag->add(UserErrors::WEAK_PASSWORD, 'password');
        $bag->add(OrderErrors::NOT_FOUND, 'order_id');

        // Frontend groups by context
        $grouped = [];
        foreach ($bag->all() as $error) {
            $key = $error->context ?? '_global';
            $grouped[$key][] = $error->toArray();
        }

        self::assertCount(2, $grouped['email']);
        self::assertCount(1, $grouped['password']);
        self::assertCount(1, $grouped['order_id']);
    }

    #[Test]
    public function it_produces_cli_format(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::INVALID_EMAIL, 'email');
        $bag->add(UserErrors::WEAK_PASSWORD, 'password', ['min' => 8]);

        $lines = [];
        foreach ($bag->all() as $error) {
            $ctx = $error->context !== null ? " (context: {$error->context})" : '';
            $lines[] = "[{$error->code}] {$error->message}{$ctx}";
        }

        self::assertSame('[00010003] Invalid email address (context: email)', $lines[0]);
        self::assertSame('[00010004] Password must be at least 8 characters (context: password)', $lines[1]);
    }

    #[Test]
    public function it_handles_global_errors_without_context(): void
    {
        $bag = new ErrorBag();
        $bag->add(OrderErrors::PAYMENT_FAILED);

        $entry = $bag->first();
        self::assertNotNull($entry);
        self::assertNull($entry->context);
        self::assertSame('errors.order.payment_failed', $entry->description);
        self::assertSame(500, $entry->httpStatus);
    }

    #[Test]
    public function it_preserves_params_through_serialization(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::WEAK_PASSWORD, 'password', [
            'min' => 8,
            'max' => 128,
            'require_upper' => true,
        ]);

        $json = json_encode($bag->toArray(), JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(8, $decoded[0]['params']['min']);
        self::assertSame(128, $decoded[0]['params']['max']);
        self::assertTrue($decoded[0]['params']['require_upper']);
    }

    #[Test]
    public function it_works_with_raw_entries_and_enum_entries(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::NOT_FOUND, 'user_id');
        $bag->addEntry(new ErrorEntry(
            code: 'CUSTOM001',
            message: 'Custom error',
            description: 'errors.custom',
            type: ErrorType::SERVER,
            httpStatus: 500,
            context: 'system',
        ));

        self::assertSame(2, $bag->count());
        self::assertSame('00010001', $bag->all()[0]->code);
        self::assertSame('CUSTOM001', $bag->all()[1]->code);
    }

    private function simulateRegistration(string $email, string $password): ErrorBag
    {
        $errors = new ErrorBag();

        if (!str_contains($email, '@') || !str_contains($email, '.')) {
            $errors->add(UserErrors::INVALID_EMAIL, 'email');
        }

        if (strlen($password) < 8) {
            $errors->add(UserErrors::WEAK_PASSWORD, 'password', ['min' => 8]);
        }

        return $errors;
    }
}
