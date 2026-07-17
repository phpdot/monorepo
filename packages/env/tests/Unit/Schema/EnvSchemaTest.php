<?php

declare(strict_types=1);

namespace PHPdot\Env\Tests\Unit\Schema;

use PHPdot\Env\Enum\AppEnv;
use PHPdot\Env\Enum\EnvType;
use PHPdot\Env\Exception\SchemaException;
use PHPdot\Env\Exception\ValidationException;
use PHPdot\Env\Schema\EnvSchema;
use PHPdot\Env\Tests\Stubs\Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnvSchemaTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../../Fixtures/';

    #[Test]
    public function loadFromArray(): void
    {
        $schema = new EnvSchema([
            'FOO' => ['type' => EnvType::STRING, 'default' => 'bar'],
        ]);

        self::assertTrue($schema->has('FOO'));
        self::assertSame('bar', $schema->getDefault('FOO'));
    }

    #[Test]
    public function loadFromFile(): void
    {
        $schema = new EnvSchema(self::FIXTURES . 'schema.basic.php');

        self::assertTrue($schema->has('APP_NAME'));
        self::assertTrue($schema->has('APP_PORT'));
        self::assertTrue($schema->has('DB_HOST'));
    }

    #[Test]
    public function loadFromMissingFileThrowsSchemaException(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('Schema file not found');

        new EnvSchema('/nonexistent/path/schema.php');
    }

    #[Test]
    public function hasReturnsTrueForExisting(): void
    {
        $schema = new EnvSchema(['FOO' => ['default' => 'x']]);

        self::assertTrue($schema->has('FOO'));
    }

    #[Test]
    public function hasReturnsFalseForMissing(): void
    {
        $schema = new EnvSchema(['FOO' => ['default' => 'x']]);

        self::assertFalse($schema->has('BAR'));
    }

    #[Test]
    public function getDefinitionThrowsForUnknownKey(): void
    {
        $schema = new EnvSchema(['FOO' => ['default' => 'x']]);

        $this->expectException(SchemaException::class);
        $schema->getDefinition('UNKNOWN');
    }

    #[Test]
    public function getTypeReturnsCorrectType(): void
    {
        $schema = new EnvSchema([
            'PORT' => ['type' => EnvType::INT, 'default' => 80],
        ]);

        self::assertSame(EnvType::INT, $schema->getType('PORT'));
    }

    #[Test]
    public function getDefaultReturnsDefault(): void
    {
        $schema = new EnvSchema([
            'NAME' => ['default' => 'default_value'],
        ]);

        self::assertSame('default_value', $schema->getDefault('NAME'));
    }

    #[Test]
    public function isRequiredReturnsCorrectly(): void
    {
        $schema = new EnvSchema([
            'REQUIRED_KEY' => ['required' => true],
            'OPTIONAL_KEY' => ['default' => 'x'],
        ]);

        self::assertTrue($schema->isRequired('REQUIRED_KEY'));
        self::assertFalse($schema->isRequired('OPTIONAL_KEY'));
    }

    #[Test]
    public function isSensitiveReturnsCorrectly(): void
    {
        $schema = new EnvSchema([
            'SECRET' => ['sensitive' => true, 'default' => 'x'],
            'PUBLIC' => ['default' => 'x'],
        ]);

        self::assertTrue($schema->isSensitive('SECRET'));
        self::assertFalse($schema->isSensitive('PUBLIC'));
    }

    #[Test]
    public function getKeysReturnsAllKeys(): void
    {
        $schema = new EnvSchema([
            'A' => ['default' => '1'],
            'B' => ['default' => '2'],
            'C' => ['default' => '3'],
        ]);

        self::assertSame(['A', 'B', 'C'], $schema->getKeys());
    }

    #[Test]
    public function castValueStringPassthrough(): void
    {
        $schema = new EnvSchema([
            'NAME' => ['type' => EnvType::STRING, 'default' => ''],
        ]);

        self::assertSame('hello', $schema->castValue('NAME', 'hello'));
    }

    #[Test]
    public function castValueIntValid(): void
    {
        $schema = new EnvSchema([
            'PORT' => ['type' => EnvType::INT, 'default' => 0],
        ]);

        self::assertSame(8080, $schema->castValue('PORT', '8080'));
    }

    #[Test]
    public function castValueIntInvalidThrows(): void
    {
        $schema = new EnvSchema([
            'PORT' => ['type' => EnvType::INT, 'default' => 0],
        ]);

        $this->expectException(ValidationException::class);
        $schema->castValue('PORT', 'not_a_number');
    }

    #[Test]
    public function castValueFloatValid(): void
    {
        $schema = new EnvSchema([
            'RATE' => ['type' => EnvType::FLOAT, 'default' => 0.0],
        ]);

        self::assertSame(1.5, $schema->castValue('RATE', '1.5'));
    }

    #[Test]
    public function castValueFloatInvalidThrows(): void
    {
        $schema = new EnvSchema([
            'RATE' => ['type' => EnvType::FLOAT, 'default' => 0.0],
        ]);

        $this->expectException(ValidationException::class);
        $schema->castValue('RATE', 'not_a_float');
    }

    #[Test]
    #[DataProvider('boolTrueProvider')]
    public function castValueBoolTrueValues(string $raw): void
    {
        $schema = new EnvSchema([
            'FLAG' => ['type' => EnvType::BOOL, 'default' => false],
        ]);

        self::assertTrue($schema->castValue('FLAG', $raw));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function boolTrueProvider(): array
    {
        return [
            'true' => ['true'],
            '1' => ['1'],
            'yes' => ['yes'],
            'on' => ['on'],
        ];
    }

    #[Test]
    #[DataProvider('boolFalseProvider')]
    public function castValueBoolFalseValues(string $raw): void
    {
        $schema = new EnvSchema([
            'FLAG' => ['type' => EnvType::BOOL, 'default' => true],
        ]);

        self::assertFalse($schema->castValue('FLAG', $raw));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function boolFalseProvider(): array
    {
        return [
            'false' => ['false'],
            '0' => ['0'],
            'no' => ['no'],
            'off' => ['off'],
        ];
    }

    #[Test]
    public function castValueBoolInvalidThrows(): void
    {
        $schema = new EnvSchema([
            'FLAG' => ['type' => EnvType::BOOL, 'default' => false],
        ]);

        $this->expectException(ValidationException::class);
        $schema->castValue('FLAG', 'maybe');
    }

    #[Test]
    public function castValueEnumValid(): void
    {
        $schema = new EnvSchema([
            'STATUS' => ['enum' => Status::class, 'default' => Status::ACTIVE],
        ]);

        self::assertSame(Status::ACTIVE, $schema->castValue('STATUS', 'active'));
    }

    #[Test]
    public function castValueEnumInvalidThrows(): void
    {
        $schema = new EnvSchema([
            'STATUS' => ['enum' => Status::class, 'default' => Status::ACTIVE],
        ]);

        $this->expectException(ValidationException::class);
        $schema->castValue('STATUS', 'unknown');
    }

    #[Test]
    public function castValueListWithCommaSeparator(): void
    {
        $schema = new EnvSchema([
            'ITEMS' => ['type' => EnvType::LIST, 'default' => []],
        ]);

        self::assertSame(['a', 'b', 'c'], $schema->castValue('ITEMS', 'a,b,c'));
    }

    #[Test]
    public function castValueListWithCustomSeparator(): void
    {
        $schema = new EnvSchema([
            'PATHS' => ['type' => EnvType::LIST, 'default' => [], 'separator' => ':'],
        ]);

        self::assertSame(['/usr/bin', '/usr/local/bin'], $schema->castValue('PATHS', '/usr/bin:/usr/local/bin'));
    }

    #[Test]
    public function castValueListEmptyStringReturnsEmpty(): void
    {
        $schema = new EnvSchema([
            'ITEMS' => ['type' => EnvType::LIST, 'default' => []],
        ]);

        self::assertSame([], $schema->castValue('ITEMS', ''));
    }

    #[Test]
    public function castValueJsonValid(): void
    {
        $schema = new EnvSchema([
            'CONFIG' => ['type' => EnvType::JSON, 'default' => []],
        ]);

        self::assertSame(['key' => 'value'], $schema->castValue('CONFIG', '{"key":"value"}'));
    }

    #[Test]
    public function castValueJsonInvalidThrows(): void
    {
        $schema = new EnvSchema([
            'CONFIG' => ['type' => EnvType::JSON, 'default' => []],
        ]);

        $this->expectException(ValidationException::class);
        $schema->castValue('CONFIG', '{invalid json}');
    }

    #[Test]
    public function castValueNullReturnsDefault(): void
    {
        $schema = new EnvSchema([
            'NAME' => ['default' => 'fallback'],
        ]);

        self::assertSame('fallback', $schema->castValue('NAME', null));
    }

    #[Test]
    public function validateConstraintsMin(): void
    {
        $schema = new EnvSchema([
            'PORT' => ['type' => EnvType::INT, 'default' => 80, 'min' => 1],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('at least');
        $schema->validateConstraints('PORT', 0);
    }

    #[Test]
    public function validateConstraintsMax(): void
    {
        $schema = new EnvSchema([
            'PORT' => ['type' => EnvType::INT, 'default' => 80, 'max' => 65535],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('at most');
        $schema->validateConstraints('PORT', 70000);
    }

    #[Test]
    public function validateConstraintsAllowed(): void
    {
        $schema = new EnvSchema([
            'LEVEL' => ['default' => 'info', 'allowed' => ['debug', 'info', 'error']],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('one of');
        $schema->validateConstraints('LEVEL', 'trace');
    }

    #[Test]
    public function validateConstraintsPattern(): void
    {
        $schema = new EnvSchema([
            'URL' => ['default' => 'http://x', 'pattern' => '/^https?:\/\//'],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('pattern');
        $schema->validateConstraints('URL', 'ftp://invalid');
    }

    #[Test]
    public function validateConstraintsNotEmptyCamelCase(): void
    {
        $schema = new EnvSchema([
            'KEY' => ['type' => EnvType::STRING, 'required' => true, 'notEmpty' => true],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must not be empty');
        $schema->castValue('KEY', '   ');
    }

    #[Test]
    public function validateConstraintsNotEmptySnakeCase(): void
    {
        $schema = new EnvSchema([
            'KEY' => ['type' => EnvType::STRING, 'required' => true, 'not_empty' => true],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must not be empty');
        $schema->castValue('KEY', '   ');
    }

    #[Test]
    public function serializeValueBool(): void
    {
        $schema = new EnvSchema([
            'FLAG' => ['type' => EnvType::BOOL, 'default' => false],
        ]);

        self::assertSame('true', $schema->serializeValue('FLAG', true));
        self::assertSame('false', $schema->serializeValue('FLAG', false));
    }

    #[Test]
    public function serializeValueEnum(): void
    {
        $schema = new EnvSchema([
            'STATUS' => ['enum' => Status::class, 'default' => Status::ACTIVE],
        ]);

        self::assertSame('active', $schema->serializeValue('STATUS', Status::ACTIVE));
    }

    #[Test]
    public function serializeValueList(): void
    {
        $schema = new EnvSchema([
            'ITEMS' => ['type' => EnvType::LIST, 'default' => []],
        ]);

        self::assertSame('a,b,c', $schema->serializeValue('ITEMS', ['a', 'b', 'c']));
    }

    #[Test]
    public function serializeValueJson(): void
    {
        $schema = new EnvSchema([
            'CONFIG' => ['type' => EnvType::JSON, 'default' => []],
        ]);

        self::assertSame('{"key":"value"}', $schema->serializeValue('CONFIG', ['key' => 'value']));
    }

    #[Test]
    public function serializeValueString(): void
    {
        $schema = new EnvSchema([
            'NAME' => ['default' => ''],
        ]);

        self::assertSame('hello', $schema->serializeValue('NAME', 'hello'));
    }

    #[Test]
    public function serializeValueInt(): void
    {
        $schema = new EnvSchema([
            'PORT' => ['type' => EnvType::INT, 'default' => 0],
        ]);

        self::assertSame('8080', $schema->serializeValue('PORT', 8080));
    }

    #[Test]
    public function serializeValueFloat(): void
    {
        $schema = new EnvSchema([
            'RATE' => ['type' => EnvType::FLOAT, 'default' => 0.0],
        ]);

        self::assertSame('1.5', $schema->serializeValue('RATE', 1.5));
    }

    #[Test]
    public function typeAutoInferenceEnumKey(): void
    {
        $schema = new EnvSchema([
            'APP_ENV' => ['enum' => AppEnv::class, 'default' => AppEnv::DEVELOPMENT],
        ]);

        self::assertSame(EnvType::ENUM, $schema->getType('APP_ENV'));
    }

    #[Test]
    public function validateConstraintsPassesWhenValid(): void
    {
        $schema = new EnvSchema([
            'PORT' => ['type' => EnvType::INT, 'default' => 80, 'min' => 1, 'max' => 65535],
        ]);

        $schema->validateConstraints('PORT', 8080);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function castValueIntRejectsFloat(): void
    {
        $schema = new EnvSchema([
            'PORT' => ['type' => EnvType::INT, 'default' => 0],
        ]);

        $this->expectException(ValidationException::class);
        $schema->castValue('PORT', '3.14');
    }

    #[Test]
    public function validateRawReturnsTrueForValid(): void
    {
        $schema = new EnvSchema([
            'PORT' => ['type' => EnvType::INT, 'default' => 80, 'min' => 1, 'max' => 65535],
        ]);

        self::assertTrue($schema->validateRaw('PORT', '8080'));
    }

    #[Test]
    public function validateRawReturnsFalseForInvalid(): void
    {
        $schema = new EnvSchema([
            'PORT' => ['type' => EnvType::INT, 'default' => 80, 'min' => 1, 'max' => 65535],
        ]);

        self::assertFalse($schema->validateRaw('PORT', 'not_a_number'));
    }
}
