<?php

declare(strict_types=1);

namespace PHPdot\I18n\Tests\Unit;

use PHPdot\I18n\ICUValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ICUValidatorTest extends TestCase
{
    private ICUValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ICUValidator();
    }

    // --- validate ---

    #[Test]
    public function validSimpleTemplate(): void
    {
        self::assertSame([], $this->validator->validate('Welcome, {name}!'));
    }

    #[Test]
    public function validPluralTemplate(): void
    {
        self::assertSame([], $this->validator->validate('{count, plural, one {# item} other {# items}}'));
    }

    #[Test]
    public function validSelectTemplate(): void
    {
        self::assertSame([], $this->validator->validate('{gender, select, male {He} female {She} other {They}}'));
    }

    #[Test]
    public function validPlainTextWithoutPlaceholders(): void
    {
        self::assertSame([], $this->validator->validate('Hello world'));
    }

    #[Test]
    public function validWithMultipleNamedParameters(): void
    {
        $template = 'Hello {firstName} {lastName}, you have {count, plural, one {# message} other {# messages}}.';
        self::assertSame([], $this->validator->validate($template));
    }

    #[Test]
    public function validNestedSelect(): void
    {
        $template = '{gender, select, male {{count, plural, one {He has # item} other {He has # items}}} other {{count, plural, one {They have # item} other {They have # items}}}}';
        self::assertSame([], $this->validator->validate($template));
    }

    #[Test]
    public function invalidMissingClosingBrace(): void
    {
        $errors = $this->validator->validate('{count, plural, one {# item}');
        self::assertNotEmpty($errors);
    }

    #[Test]
    public function invalidMissingOpeningBrace(): void
    {
        $errors = $this->validator->validate('count, plural, one {# item}}');
        self::assertNotEmpty($errors);
    }

    #[Test]
    public function invalidUnknownFormatType(): void
    {
        $errors = $this->validator->validate('{count, badformat, one {x}}');
        self::assertNotEmpty($errors);
    }

    #[Test]
    public function emptyStringIsInvalid(): void
    {
        $errors = $this->validator->validate('');
        self::assertNotEmpty($errors);
    }

    #[Test]
    public function validWithDifferentLocale(): void
    {
        self::assertSame([], $this->validator->validate('{count, plural, one {# عنصر} other {# عناصر}}', 'ar'));
    }

    #[Test]
    public function validWithLocaleRegion(): void
    {
        self::assertSame([], $this->validator->validate('Hello {name}', 'en_US'));
    }

    // --- isValid ---

    #[Test]
    public function isValidReturnsTrueForValid(): void
    {
        self::assertTrue($this->validator->isValid('Hello, {name}!'));
    }

    #[Test]
    public function isValidReturnsFalseForInvalid(): void
    {
        self::assertFalse($this->validator->isValid('{count, plural, one {# item}'));
    }

    #[Test]
    public function isValidReturnsTrueForPlainText(): void
    {
        self::assertTrue($this->validator->isValid('Just plain text'));
    }

    #[Test]
    public function isValidReturnsFalseForEmpty(): void
    {
        self::assertFalse($this->validator->isValid(''));
    }
}
