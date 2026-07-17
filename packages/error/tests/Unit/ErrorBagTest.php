<?php

declare(strict_types=1);

namespace PHPdot\Error\Tests\Unit;

use PHPdot\Contracts\I18n\MessageTranslatorInterface;
use PHPdot\Error\ErrorBag;
use PHPdot\Error\ErrorEntry;
use PHPdot\Error\ErrorType;
use PHPdot\Error\Tests\Fixtures\OrderErrors;
use PHPdot\Error\Tests\Fixtures\UserErrors;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ErrorBagTest extends TestCase
{
    #[Test]
    public function it_starts_empty(): void
    {
        $bag = new ErrorBag();

        self::assertFalse($bag->hasErrors());
        self::assertSame(0, $bag->count());
        self::assertSame([], $bag->all());
        self::assertNull($bag->first());
    }

    #[Test]
    public function it_adds_error_from_enum(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::NOT_FOUND, 'user_id');

        self::assertTrue($bag->hasErrors());
        self::assertSame(1, $bag->count());

        $entry = $bag->first();
        self::assertNotNull($entry);
        self::assertSame('00010001', $entry->code);
        self::assertSame('User not found', $entry->message);
        self::assertSame('errors.user.not_found', $entry->description);
        self::assertSame(ErrorType::NOT_FOUND, $entry->type);
        self::assertSame(404, $entry->httpStatus);
        self::assertSame('user_id', $entry->context);
    }

    #[Test]
    public function it_adds_error_with_params(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::WEAK_PASSWORD, 'password', ['min' => 8]);

        $entry = $bag->first();
        self::assertNotNull($entry);
        self::assertSame(['min' => 8], $entry->params);
    }

    #[Test]
    public function it_adds_error_without_context(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::NOT_FOUND);

        $entry = $bag->first();
        self::assertNotNull($entry);
        self::assertNull($entry->context);
    }

    #[Test]
    public function it_adds_raw_entry(): void
    {
        $bag = new ErrorBag();
        $entry = new ErrorEntry('custom', 'msg', 'desc', ErrorType::SERVER, 500);
        $bag->addEntry($entry);

        self::assertSame(1, $bag->count());
        self::assertSame($entry, $bag->first());
    }

    #[Test]
    public function it_adds_multiple_errors(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::INVALID_EMAIL, 'email');
        $bag->add(UserErrors::WEAK_PASSWORD, 'password');

        self::assertSame(2, $bag->count());
    }

    #[Test]
    public function it_checks_specific_error_code(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::EMAIL_TAKEN, 'email');

        self::assertTrue($bag->hasError(UserErrors::EMAIL_TAKEN));
        self::assertFalse($bag->hasError(UserErrors::NOT_FOUND));
    }

    #[Test]
    public function it_returns_first_error(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::INVALID_EMAIL, 'email');
        $bag->add(UserErrors::WEAK_PASSWORD, 'password');

        $first = $bag->first();
        self::assertNotNull($first);
        self::assertSame('00010003', $first->code);
    }

    #[Test]
    public function it_returns_all_errors(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::INVALID_EMAIL, 'email');
        $bag->add(UserErrors::WEAK_PASSWORD, 'password');

        $all = $bag->all();
        self::assertCount(2, $all);
        self::assertSame('00010003', $all[0]->code);
        self::assertSame('00010004', $all[1]->code);
    }

    #[Test]
    public function it_filters_by_context(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::INVALID_EMAIL, 'email');
        $bag->add(UserErrors::EMAIL_TAKEN, 'email');
        $bag->add(UserErrors::WEAK_PASSWORD, 'password');

        $emailErrors = $bag->forContext('email');
        self::assertCount(2, $emailErrors);
        self::assertSame('00010003', $emailErrors[0]->code);
        self::assertSame('00010002', $emailErrors[1]->code);

        $passwordErrors = $bag->forContext('password');
        self::assertCount(1, $passwordErrors);
    }

    #[Test]
    public function it_filters_by_context_returns_empty_for_no_match(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::NOT_FOUND, 'user_id');

        self::assertSame([], $bag->forContext('email'));
    }

    #[Test]
    public function it_filters_by_type(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::INVALID_EMAIL, 'email');
        $bag->add(UserErrors::WEAK_PASSWORD, 'password');
        $bag->add(UserErrors::EMAIL_TAKEN, 'email');
        $bag->add(UserErrors::NOT_FOUND, 'user_id');

        $validation = $bag->ofType(ErrorType::VALIDATION);
        self::assertCount(2, $validation);

        $notFound = $bag->ofType(ErrorType::NOT_FOUND);
        self::assertCount(1, $notFound);

        $conflict = $bag->ofType(ErrorType::CONFLICT);
        self::assertCount(1, $conflict);

        $auth = $bag->ofType(ErrorType::AUTHENTICATION);
        self::assertSame([], $auth);
    }

    #[Test]
    public function it_returns_unique_codes(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::INVALID_EMAIL, 'email');
        $bag->add(UserErrors::INVALID_EMAIL, 'backup_email');
        $bag->add(UserErrors::WEAK_PASSWORD, 'password');

        $codes = $bag->codes();
        self::assertSame(['00010003', '00010004'], $codes);
    }

    #[Test]
    public function it_merges_another_bag(): void
    {
        $bag1 = new ErrorBag();
        $bag1->add(UserErrors::INVALID_EMAIL, 'email');

        $bag2 = new ErrorBag();
        $bag2->add(OrderErrors::NOT_FOUND, 'order_id');

        $bag1->merge($bag2);

        self::assertSame(2, $bag1->count());
        self::assertSame('00010003', $bag1->all()[0]->code);
        self::assertSame('00020001', $bag1->all()[1]->code);
    }

    #[Test]
    public function it_merges_empty_bag(): void
    {
        $bag1 = new ErrorBag();
        $bag1->add(UserErrors::NOT_FOUND);

        $bag1->merge(new ErrorBag());

        self::assertSame(1, $bag1->count());
    }

    #[Test]
    public function it_clears_all_errors(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::NOT_FOUND);
        $bag->add(UserErrors::EMAIL_TAKEN);

        $bag->clear();

        self::assertFalse($bag->hasErrors());
        self::assertSame(0, $bag->count());
        self::assertSame([], $bag->all());
    }

    #[Test]
    public function it_returns_http_status_from_first_error(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::INVALID_EMAIL, 'email');  // 422
        $bag->add(UserErrors::NOT_FOUND, 'user_id');     // 404

        self::assertSame(422, $bag->getHttpStatus());
    }

    #[Test]
    public function it_returns_500_when_empty(): void
    {
        $bag = new ErrorBag();

        self::assertSame(500, $bag->getHttpStatus());
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::INVALID_EMAIL, 'email');
        $bag->add(UserErrors::WEAK_PASSWORD, 'password', ['min' => 8]);

        $array = $bag->toArray();

        self::assertCount(2, $array);
        self::assertSame('00010003', $array[0]['code']);
        self::assertSame('email', $array[0]['context']);
        self::assertSame('00010004', $array[1]['code']);
        self::assertSame('password', $array[1]['context']);
        self::assertSame(['min' => 8], $array[1]['params']);
    }

    #[Test]
    public function it_converts_empty_bag_to_array(): void
    {
        $bag = new ErrorBag();

        self::assertSame([], $bag->toArray());
    }

    #[Test]
    public function it_is_chainable(): void
    {
        $bag = new ErrorBag();

        $result = $bag
            ->add(UserErrors::INVALID_EMAIL, 'email')
            ->add(UserErrors::WEAK_PASSWORD, 'password')
            ->addEntry(new ErrorEntry('c', 'm', 'd', ErrorType::SERVER, 500));

        self::assertSame($bag, $result);
        self::assertSame(3, $bag->count());
    }

    #[Test]
    public function it_works_with_cross_module_errors(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::NOT_FOUND, 'user_id');
        $bag->add(OrderErrors::PAYMENT_FAILED, 'stripe');
        $bag->add(UserErrors::LOCKED);

        self::assertSame(3, $bag->count());

        $codes = $bag->codes();
        self::assertContains('00010001', $codes);
        self::assertContains('00020003', $codes);
        self::assertContains('00010005', $codes);
    }

    #[Test]
    public function it_handles_various_context_types(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::NOT_FOUND, 'user_id');              // route param
        $bag->add(UserErrors::INVALID_EMAIL, 'email');             // form field
        $bag->add(UserErrors::LOCKED, 'Authorization');            // header
        $bag->add(OrderErrors::PAYMENT_FAILED, 'stripe');          // service
        $bag->add(UserErrors::WEAK_PASSWORD, 'address.zip_code'); // nested path

        self::assertCount(1, $bag->forContext('user_id'));
        self::assertCount(1, $bag->forContext('email'));
        self::assertCount(1, $bag->forContext('Authorization'));
        self::assertCount(1, $bag->forContext('stripe'));
        self::assertCount(1, $bag->forContext('address.zip_code'));
    }

    #[Test]
    public function clear_returns_self(): void
    {
        $bag = new ErrorBag();
        $result = $bag->clear();

        self::assertSame($bag, $result);
    }

    #[Test]
    public function merge_returns_self(): void
    {
        $bag = new ErrorBag();
        $result = $bag->merge(new ErrorBag());

        self::assertSame($bag, $result);
    }

    #[Test]
    public function it_keeps_description_as_key_when_no_translator(): void
    {
        $bag = new ErrorBag();
        $bag->add(UserErrors::NOT_FOUND, 'user_id');

        $entry = $bag->first();
        self::assertNotNull($entry);
        self::assertSame('errors.user.not_found', $entry->description);
        self::assertSame('User not found', $entry->message);
    }

    #[Test]
    public function it_translates_description_when_translator_wired(): void
    {
        $translator = new class implements MessageTranslatorInterface {
            public function translate(string $key, array $params = []): string
            {
                return 'TRANSLATED:' . $key . ':' . json_encode($params);
            }
        };

        $bag = new ErrorBag($translator);
        $bag->add(UserErrors::EMAIL_TAKEN, 'email', ['email' => 'a@b.c']);

        $entry = $bag->first();
        self::assertNotNull($entry);
        self::assertSame('TRANSLATED:errors.user.email_taken:{"email":"a@b.c"}', $entry->description);
        // message field is untouched — always the enum's English string
        self::assertSame('Email is already taken', $entry->message);
    }

    #[Test]
    public function it_passes_translator_output_through_unchanged(): void
    {
        // Translator returns "[key]" for missing keys — bag must not second-guess.
        $translator = new class implements MessageTranslatorInterface {
            public function translate(string $key, array $params = []): string
            {
                return '[' . $key . ']';
            }
        };

        $bag = new ErrorBag($translator);
        $bag->add(UserErrors::NOT_FOUND);

        $entry = $bag->first();
        self::assertNotNull($entry);
        self::assertSame('[errors.user.not_found]', $entry->description);
    }
}
