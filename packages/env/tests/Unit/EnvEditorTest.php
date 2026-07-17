<?php

declare(strict_types=1);

namespace PHPdot\Env\Tests\Unit;

use PHPdot\Env\EnvEditor;
use PHPdot\Env\Exception\SchemaException;
use PHPdot\Env\Schema\EnvSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnvEditorTest extends TestCase
{
    private string $tempPath;
    private EnvSchema $schema;

    protected function setUp(): void
    {
        $this->tempPath = sys_get_temp_dir() . '/env_editor_test_' . uniqid();
        $this->schema = new EnvSchema([
            'APP_NAME' => ['default' => 'test'],
            'APP_PORT' => ['default' => '3000'],
            'DB_HOST' => ['default' => 'localhost'],
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempPath)) {
            unlink($this->tempPath);
        }
    }

    #[Test]
    public function setStoresPendingValue(): void
    {
        $editor = new EnvEditor($this->tempPath, $this->schema);
        $editor->set('APP_NAME', 'NewApp');

        self::assertTrue($editor->hasKey('APP_NAME'));
    }

    #[Test]
    public function removeStagesRemoval(): void
    {
        file_put_contents($this->tempPath, "APP_NAME=OldApp\n");

        $editor = new EnvEditor($this->tempPath, $this->schema);
        $editor->remove('APP_NAME');
        $editor->save();

        $content = file_get_contents($this->tempPath);
        self::assertIsString($content);
        self::assertStringNotContainsString('APP_NAME', $content);
    }

    #[Test]
    public function saveWritesToFile(): void
    {
        $editor = new EnvEditor($this->tempPath, $this->schema);
        $editor->set('APP_NAME', 'WrittenApp');
        $editor->save();

        $content = file_get_contents($this->tempPath);
        self::assertIsString($content);
        self::assertStringContainsString('APP_NAME=WrittenApp', $content);
    }

    #[Test]
    public function savePreservesComments(): void
    {
        file_put_contents($this->tempPath, "# This is a comment\nAPP_NAME=OldApp\n");

        $editor = new EnvEditor($this->tempPath, $this->schema);
        $editor->set('APP_NAME', 'NewApp');
        $editor->save();

        $content = file_get_contents($this->tempPath);
        self::assertIsString($content);
        self::assertStringContainsString('# This is a comment', $content);
    }

    #[Test]
    public function savePreservesBlankLines(): void
    {
        file_put_contents($this->tempPath, "APP_NAME=OldApp\n\nDB_HOST=localhost\n");

        $editor = new EnvEditor($this->tempPath, $this->schema);
        $editor->set('APP_NAME', 'NewApp');
        $editor->save();

        $content = file_get_contents($this->tempPath);
        self::assertIsString($content);
        self::assertStringContainsString("\n\n", $content);
    }

    #[Test]
    public function saveAppendsNewKeys(): void
    {
        file_put_contents($this->tempPath, "APP_NAME=OldApp\n");

        $editor = new EnvEditor($this->tempPath, $this->schema);
        $editor->set('DB_HOST', 'newhost');
        $editor->save();

        $content = file_get_contents($this->tempPath);
        self::assertIsString($content);
        self::assertStringContainsString('APP_NAME=OldApp', $content);
        self::assertStringContainsString('DB_HOST=newhost', $content);
    }

    #[Test]
    public function saveRemovesStagedKeys(): void
    {
        file_put_contents($this->tempPath, "APP_NAME=OldApp\nDB_HOST=localhost\n");

        $editor = new EnvEditor($this->tempPath, $this->schema);
        $editor->remove('APP_NAME');
        $editor->save();

        $content = file_get_contents($this->tempPath);
        self::assertIsString($content);
        self::assertStringNotContainsString('APP_NAME', $content);
        self::assertStringContainsString('DB_HOST=localhost', $content);
    }

    #[Test]
    public function hasKeyChecksFileAndPending(): void
    {
        file_put_contents($this->tempPath, "APP_NAME=InFile\n");

        $editor = new EnvEditor($this->tempPath, $this->schema);

        self::assertTrue($editor->hasKey('APP_NAME'));
        self::assertFalse($editor->hasKey('DB_HOST'));

        $editor->set('DB_HOST', 'newhost');
        self::assertTrue($editor->hasKey('DB_HOST'));
    }

    #[Test]
    public function resetClearsPending(): void
    {
        $editor = new EnvEditor($this->tempPath, $this->schema);
        $editor->set('APP_NAME', 'Pending');

        self::assertTrue($editor->hasKey('APP_NAME'));

        $editor->reset();

        self::assertFalse($editor->hasKey('APP_NAME'));
    }

    #[Test]
    public function setValidatesAgainstSchemaUnknownKeyThrows(): void
    {
        $editor = new EnvEditor($this->tempPath, $this->schema);

        $this->expectException(SchemaException::class);
        $editor->set('UNKNOWN_KEY', 'value');
    }
}
