<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Integration;

use PHPdot\Error\ErrorBag;
use PHPdot\Validator\Rule;
use PHPdot\Validator\Rule\Confirmed;
use PHPdot\Validator\Rule\Email;
use PHPdot\Validator\Rule\In;
use PHPdot\Validator\Rule\Max;
use PHPdot\Validator\Rule\Min;
use PHPdot\Validator\Rule\Regex;
use PHPdot\Validator\Rule\Required;
use PHPdot\Validator\Rule\StringType;
use PHPdot\Validator\Rule\Unique;
use PHPdot\Validator\Tests\Stubs\TestErrorCode;
use PHPdot\Validator\Validator;
use PHPUnit\Framework\TestCase;

final class SignupFormTest extends TestCase
{
    private Validator $validator;

    /** @var array<string, true> */
    private array $takenUsernames;

    protected function setUp(): void
    {
        $this->validator = new Validator(new ErrorBag());
        $this->takenUsernames = ['admin' => true, 'omar' => true];
    }

    /**
     * @return array<string, list<\PHPdot\Validator\Contract\RuleInterface>>
     */
    private function rules(): array
    {
        return [
            'username' => [
                (new Required())->withError(TestErrorCode::UsernameRequired),
                (new StringType())->withError(TestErrorCode::UsernameRequired),
                (new Min(3))->withError(TestErrorCode::UsernameTooShort),
                (new Max(50))->withError(TestErrorCode::UsernameTooLong),
                (new Unique(fn(mixed $v): bool => isset($this->takenUsernames[(string) $v])))
                    ->withError(TestErrorCode::Generic),
            ],
            'email' => [
                (new Required())->withError(TestErrorCode::EmailRequired),
                (new Email())->withError(TestErrorCode::EmailInvalid),
            ],
            'role' => [
                (new Required())->withError(TestErrorCode::RoleInvalid),
                (new In('admin', 'editor', 'viewer'))->withError(TestErrorCode::RoleInvalid),
            ],
            'password' => [
                (new Required())->withError(TestErrorCode::PasswordWeak),
                (new Min(8))->withError(TestErrorCode::PasswordWeak),
                (new Regex('/[a-zA-Z]/'))->withError(TestErrorCode::PasswordWeak),
                (new Regex('/[0-9]/'))->withError(TestErrorCode::PasswordWeak),
                (new Confirmed())->withError(TestErrorCode::PasswordMismatch),
            ],
        ];
    }

    public function test_valid_signup_payload_produces_no_errors(): void
    {
        $bag = $this->validator->validate([
            'username' => 'newuser',
            'email' => 'new@example.com',
            'role' => 'editor',
            'password' => 'Sup3rSecret',
            'password_confirmation' => 'Sup3rSecret',
        ], $this->rules());

        self::assertFalse($bag->hasErrors());
    }

    public function test_missing_required_fields_collect_errors(): void
    {
        $bag = $this->validator->validate([], $this->rules());

        self::assertTrue($bag->hasErrors());
        self::assertNotEmpty($bag->forContext('username'));
        self::assertNotEmpty($bag->forContext('email'));
        self::assertNotEmpty($bag->forContext('role'));
        self::assertNotEmpty($bag->forContext('password'));
    }

    public function test_username_messages_use_developer_supplied_codes(): void
    {
        $bag = $this->validator->validate([
            'username' => '',
            'email' => 'a@b.com',
            'role' => 'editor',
            'password' => 'Sup3rSecret',
            'password_confirmation' => 'Sup3rSecret',
        ], $this->rules());

        $usernameErrors = $bag->forContext('username');

        self::assertNotEmpty($usernameErrors);
        self::assertSame(TestErrorCode::UsernameRequired->value, $usernameErrors[0]->code);
        self::assertSame('Username is required.', $usernameErrors[0]->message);
    }

    public function test_taken_username_triggers_unique_rule(): void
    {
        $bag = $this->validator->validate([
            'username' => 'admin',
            'email' => 'a@b.com',
            'role' => 'editor',
            'password' => 'Sup3rSecret',
            'password_confirmation' => 'Sup3rSecret',
        ], $this->rules());

        $usernameErrors = $bag->forContext('username');

        self::assertNotEmpty($usernameErrors);
        self::assertSame(TestErrorCode::Generic->value, $usernameErrors[0]->code);
    }

    public function test_password_mismatch_uses_confirmed_rule(): void
    {
        $bag = $this->validator->validate([
            'username' => 'newuser',
            'email' => 'a@b.com',
            'role' => 'editor',
            'password' => 'Sup3rSecret',
            'password_confirmation' => 'Different',
        ], $this->rules());

        $passwordErrors = $bag->forContext('password');

        self::assertNotEmpty($passwordErrors);
        $codes = array_map(fn($e): string => $e->code, $passwordErrors);
        self::assertContains(TestErrorCode::PasswordMismatch->value, $codes);
    }

    public function test_invalid_role_returns_role_invalid_code(): void
    {
        $bag = $this->validator->validate([
            'username' => 'newuser',
            'email' => 'a@b.com',
            'role' => 'superhero',
            'password' => 'Sup3rSecret',
            'password_confirmation' => 'Sup3rSecret',
        ], $this->rules());

        $roleErrors = $bag->forContext('role');

        self::assertNotEmpty($roleErrors);
        self::assertSame(TestErrorCode::RoleInvalid->value, $roleErrors[0]->code);
    }

    public function test_bag_serializes_to_array_for_json_or_flash(): void
    {
        $bag = $this->validator->validate([
            'username' => 'ad',
            'email' => 'invalid',
            'role' => 'editor',
            'password' => 'Sup3rSecret',
            'password_confirmation' => 'Sup3rSecret',
        ], $this->rules());

        $payload = $bag->toArray();

        self::assertNotEmpty($payload);

        foreach ($payload as $entry) {
            self::assertArrayHasKey('code', $entry);
            self::assertArrayHasKey('message', $entry);
            self::assertArrayHasKey('context', $entry);
            self::assertArrayHasKey('params', $entry);
            self::assertArrayHasKey('httpStatus', $entry);
        }
    }

    public function test_http_status_is_validation_default(): void
    {
        $bag = $this->validator->validate([], $this->rules());

        self::assertSame(422, $bag->getHttpStatus());
    }

    public function test_closure_rule_for_business_logic(): void
    {
        $bag = $this->validator->validate(
            ['username' => 'forbidden'],
            [
                'username' => [
                    Rule::closure(static fn(mixed $v): bool => $v !== 'forbidden')
                        ->withError(TestErrorCode::Generic),
                ],
            ],
        );

        self::assertTrue($bag->hasErrors());
        self::assertSame(TestErrorCode::Generic->value, $bag->all()[0]->code);
    }
}
